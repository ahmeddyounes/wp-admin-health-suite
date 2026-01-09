/**
 * Media Audit JavaScript
 *
 * Handles media audit scanning, filtering, sorting, and bulk operations.
 *
 * @param $
 * @package
 */

(function ($) {
	'use strict';

	const MediaAudit = {
		/**
		 * Current tab.
		 */
		currentTab: 'unused',

		/**
		 * Items per page.
		 */
		itemsPerPage: 50,

		/**
		 * Current page for each tab.
		 */
		currentPages: {
			unused: 1,
			duplicates: 1,
			'large-files': 1,
			'missing-alt': 1,
		},

		/**
		 * Data cache for each tab.
		 */
		dataCache: {
			stats: null,
			unused: [],
			duplicates: [],
			'large-files': [],
			'missing-alt': [],
		},

		/**
		 * Current sort for each tab.
		 */
		currentSort: {
			unused: { field: 'date', order: 'desc' },
			duplicates: { field: 'size', order: 'desc' },
			'large-files': { field: 'size', order: 'desc' },
			'missing-alt': { field: 'date', order: 'desc' },
		},

		/**
		 * Current filters for each tab.
		 */
		currentFilters: {
			unused: { search: '', type: '' },
			duplicates: { search: '' },
			'large-files': { search: '', size: '' },
			'missing-alt': { search: '' },
		},

		/**
		 * Selected items for each tab.
		 */
		selectedItems: {
			unused: new Set(),
			duplicates: new Set(),
			'large-files': new Set(),
			'missing-alt': new Set(),
		},

		/**
		 * Initialize the media audit module.
		 */
		init() {
			this.cacheDom();
			this.bindEvents();
			this.loadStats();
			this.loadTabData(this.currentTab);
		},

		/**
		 * Cache DOM elements.
		 */
		cacheDom() {
			this.$scanBanner = $('.wpha-scan-status-banner');
			this.$rescanBtn = $('.wpha-rescan-btn');
			this.$statsCards = $('.wpha-stats-overview');
			this.$tabBtns = $('.wpha-tab-btn');
			this.$tabPanels = $('.wpha-tab-panel');
		},

		/**
		 * Bind events.
		 */
		bindEvents() {
			// Tab switching
			$(document).on(
				'click',
				'.wpha-tab-btn',
				this.handleTabSwitch.bind(this)
			);

			// Rescan button
			$(document).on(
				'click',
				'.wpha-rescan-btn',
				this.handleRescan.bind(this)
			);

			// Sorting
			$(document).on(
				'click',
				'.wpha-sortable',
				this.handleSort.bind(this)
			);

			// Filtering
			$(document).on(
				'input',
				'.wpha-search-input',
				this.handleSearchFilter.bind(this)
			);
			$(document).on(
				'change',
				'.wpha-filter-select',
				this.handleTypeFilter.bind(this)
			);
			$(document).on(
				'change',
				'.wpha-size-filter-select',
				this.handleSizeFilter.bind(this)
			);

			// Checkbox selection
			$(document).on(
				'change',
				'.wpha-select-all',
				this.handleSelectAll.bind(this)
			);
			$(document).on(
				'change',
				'.wpha-item-checkbox',
				this.handleItemSelect.bind(this)
			);

			// Bulk actions
			$(document).on(
				'click',
				'.wpha-bulk-apply-btn',
				this.handleBulkAction.bind(this)
			);

			// Individual actions
			$(document).on(
				'click',
				'.wpha-delete-btn',
				this.handleDelete.bind(this)
			);
			$(document).on(
				'click',
				'.wpha-ignore-btn',
				this.handleIgnore.bind(this)
			);
			$(document).on(
				'click',
				'.wpha-keep-original-btn',
				this.handleKeepOriginal.bind(this)
			);

			// Pagination
			$(document).on(
				'click',
				'.wpha-page-btn',
				this.handlePageChange.bind(this)
			);
		},

		/**
		 * Load statistics.
		 */
		loadStats() {
			wp.apiFetch({
				path: '/wpha/v1/media/stats',
			})
				.then((response) => {
					if (response.success && response.data) {
						this.dataCache.stats = response.data;
						this.renderStats(response.data);
						this.renderScanStatus(response.data);
					}
				})
				.catch((error) => {
					console.error('Error loading stats:', error);
					this.hideSkeletons(this.$statsCards);
					this.hideSkeletons(this.$scanBanner);
				});
		},

		/**
		 * Render statistics cards.
		 * @param data
		 */
		renderStats(data) {
			const stats = [
				{ value: data.total_files || 0, selector: 0 },
				{ value: data.unused_count || 0, selector: 1 },
				{ value: data.duplicates_count || 0, selector: 2 },
				{
					value: this.formatBytes(data.potential_savings || 0),
					selector: 3,
				},
			];

			stats.forEach((stat) => {
				const $card = this.$statsCards
					.find('.wpha-stat-card')
					.eq(stat.selector);
				$card.find('.wpha-stat-value').text(stat.value);
				$card.find('.wpha-stat-card-skeleton').hide();
				$card.find('.wpha-stat-card-content').show();
			});

			// Update tab badges
			$('.wpha-tab-btn[data-tab="unused"] .wpha-tab-badge').text(
				data.unused_count || 0
			);
			$('.wpha-tab-btn[data-tab="duplicates"] .wpha-tab-badge').text(
				data.duplicates_count || 0
			);
			$('.wpha-tab-btn[data-tab="large-files"] .wpha-tab-badge').text(
				data.large_files_count || 0
			);
			$('.wpha-tab-btn[data-tab="missing-alt"] .wpha-tab-badge').text(
				data.missing_alt_count || 0
			);
		},

		/**
		 * Render scan status banner.
		 * @param data
		 */
		renderScanStatus(data) {
			const lastScan = data.last_scan
				? this.formatDate(data.last_scan)
				: wpAdminHealthData.i18n.no_data || 'Never';

			this.$scanBanner.find('.wpha-scan-status-value').text(lastScan);
			this.$scanBanner.find('.wpha-scan-status-banner-skeleton').hide();
			this.$scanBanner.find('.wpha-scan-status-content').show();
			this.$rescanBtn.prop('disabled', false);
		},

		/**
		 * Handle tab switch.
		 * @param e
		 */
		handleTabSwitch(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);
			const tab = $btn.data('tab');

			if (tab === this.currentTab) {
				return;
			}

			// Update active states
			this.$tabBtns.removeClass('wpha-tab-active');
			$btn.addClass('wpha-tab-active');

			this.$tabPanels.removeClass('wpha-tab-active');
			$(`.wpha-tab-panel[data-tab="${tab}"]`).addClass('wpha-tab-active');

			this.currentTab = tab;
			this.loadTabData(tab);
		},

		/**
		 * Load data for specific tab.
		 * @param tab
		 */
		loadTabData(tab) {
			// If data is cached, just render it
			if (this.dataCache[tab].length > 0) {
				this.renderTabData(tab);
				return;
			}

			let apiPath = '';
			switch (tab) {
				case 'unused':
					apiPath = '/wpha/v1/media/unused';
					break;
				case 'duplicates':
					apiPath = '/wpha/v1/media/duplicates';
					break;
				case 'large-files':
					apiPath = '/wpha/v1/media/large-files';
					break;
				case 'missing-alt':
					apiPath = '/wpha/v1/media/missing-alt';
					break;
			}

			const $panel = $(`.wpha-tab-panel[data-tab="${tab}"]`);
			const $skeleton = $panel.find('.wpha-tab-panel-skeleton');

			$skeleton.show();

			wp.apiFetch({
				path: apiPath,
			})
				.then((response) => {
					if (response.success && response.data) {
						this.dataCache[tab] = response.data;
						this.renderTabData(tab);
					}
				})
				.catch((error) => {
					console.error(`Error loading ${tab} data:`, error);
					$skeleton.hide();
					this.showEmptyState($panel);
				});
		},

		/**
		 * Render data for specific tab.
		 * @param tab
		 */
		renderTabData(tab) {
			const $panel = $(`.wpha-tab-panel[data-tab="${tab}"]`);
			const $skeleton = $panel.find('.wpha-tab-panel-skeleton');

			$skeleton.hide();

			// Apply filters and sorting
			let data = this.filterData(tab, this.dataCache[tab]);
			data = this.sortData(tab, data);

			// Render based on tab type
			if (tab === 'duplicates') {
				this.renderDuplicates($panel, data);
			} else {
				this.renderTable($panel, tab, data);
			}

			this.renderPagination($panel, tab, data.length);
			this.updateBulkActions($panel, tab);
		},

		/**
		 * Filter data based on current filters.
		 * @param tab
		 * @param data
		 */
		filterData(tab, data) {
			const filters = this.currentFilters[tab];
			let filtered = [...data];

			// Search filter
			if (filters.search) {
				const search = filters.search.toLowerCase();
				filtered = filtered.filter(
					(item) =>
						(item.filename || '').toLowerCase().includes(search) ||
						(item.title || '').toLowerCase().includes(search)
				);
			}

			// Type filter (for unused tab)
			if (filters.type) {
				filtered = filtered.filter(
					(item) => item.type === filters.type
				);
			}

			// Size filter (for large files tab)
			if (filters.size) {
				const sizeBytes = {
					'1mb': 1024 * 1024,
					'5mb': 5 * 1024 * 1024,
					'10mb': 10 * 1024 * 1024,
				};
				const minSize = sizeBytes[filters.size] || 0;
				filtered = filtered.filter((item) => item.size > minSize);
			}

			return filtered;
		},

		/**
		 * Sort data based on current sort.
		 * @param tab
		 * @param data
		 */
		sortData(tab, data) {
			const sort = this.currentSort[tab];
			const sorted = [...data];

			sorted.sort((a, b) => {
				let aVal = a[sort.field];
				let bVal = b[sort.field];

				// Handle different data types
				if (typeof aVal === 'string') {
					aVal = aVal.toLowerCase();
					bVal = bVal.toLowerCase();
				}

				if (sort.order === 'asc') {
					return aVal > bVal ? 1 : -1;
				}
				return aVal < bVal ? 1 : -1;
			});

			return sorted;
		},

		/**
		 * Render table for tab.
		 * @param $panel
		 * @param tab
		 * @param data
		 */
		renderTable($panel, tab, data) {
			const $tbody = $panel.find('.wpha-media-table tbody');
			const page = this.currentPages[tab];
			const start = (page - 1) * this.itemsPerPage;
			const end = start + this.itemsPerPage;
			const pageData = data.slice(start, end);

			if (pageData.length === 0) {
				$tbody.html(
					'<tr><td colspan="6" class="wpha-empty-state">' +
						(wpAdminHealthData.i18n.no_data || 'No items found.') +
						'</td></tr>'
				);
				return;
			}

			const html = pageData
				.map((item) => this.renderTableRow(tab, item))
				.join('');
			$tbody.html(html);

			// Lazy load images
			this.lazyLoadPreviews($tbody);
		},

		/**
		 * Render table row.
		 * @param tab
		 * @param item
		 */
		renderTableRow(tab, item) {
			const isChecked = this.selectedItems[tab].has(item.id);
			const previewUrl = item.thumbnail || item.url || '';
			const isImage = item.type === 'image';

			let html = '<tr data-id="' + item.id + '">';
			html += '<td class="wpha-col-checkbox">';
			html +=
				'<input type="checkbox" class="wpha-item-checkbox" data-id="' +
				item.id +
				'" ' +
				(isChecked ? 'checked' : '') +
				' />';
			html += '</td>';

			html += '<td class="wpha-col-preview">';
			if (isImage && previewUrl) {
				html +=
					'<img class="wpha-lazy-preview" data-src="' +
					previewUrl +
					'" alt="" width="50" height="50" />';
			} else {
				html +=
					'<span class="dashicons dashicons-media-default"></span>';
			}
			html += '</td>';

			html += '<td class="wpha-col-filename">';
			html +=
				'<strong>' +
				this.escapeHtml(item.filename || item.title || 'Untitled') +
				'</strong>';
			html += '</td>';

			if (tab === 'large-files') {
				html +=
					'<td class="wpha-col-size">' +
					this.formatBytes(item.size || 0) +
					'</td>';
				html +=
					'<td class="wpha-col-dimensions">' +
					(item.width && item.height
						? item.width + ' × ' + item.height
						: '--') +
					'</td>';
			} else if (tab !== 'missing-alt') {
				html +=
					'<td class="wpha-col-size">' +
					this.formatBytes(item.size || 0) +
					'</td>';
				html +=
					'<td class="wpha-col-date">' +
					this.formatDate(item.date) +
					'</td>';
			} else {
				html +=
					'<td class="wpha-col-date">' +
					this.formatDate(item.date) +
					'</td>';
			}

			html += '<td class="wpha-col-actions">';
			if (tab !== 'missing-alt') {
				html +=
					'<button class="button button-small wpha-delete-btn" data-id="' +
					item.id +
					'" title="Delete">';
				html += '<span class="dashicons dashicons-trash"></span>';
				html += '</button> ';
			}
			html +=
				'<button class="button button-small wpha-ignore-btn" data-id="' +
				item.id +
				'" title="Ignore">';
			html += '<span class="dashicons dashicons-hidden"></span>';
			html += '</button>';
			html += '</td>';

			html += '</tr>';
			return html;
		},

		/**
		 * Render duplicates groups.
		 * @param $panel
		 * @param data
		 */
		renderDuplicates($panel, data) {
			const $container = $panel.find('.wpha-duplicates-container');
			const page = this.currentPages.duplicates;
			const start = (page - 1) * this.itemsPerPage;
			const end = start + this.itemsPerPage;
			const pageData = data.slice(start, end);

			if (pageData.length === 0) {
				$container.html(
					'<div class="wpha-empty-state">' +
						(wpAdminHealthData.i18n.no_data ||
							'No duplicate groups found.') +
						'</div>'
				);
				return;
			}

			const html = pageData
				.map((group) => this.renderDuplicateGroup(group))
				.join('');
			$container.html(html);

			// Lazy load images
			this.lazyLoadPreviews($container);
		},

		/**
		 * Render duplicate group.
		 * @param group
		 */
		renderDuplicateGroup(group) {
			let html = '<div class="wpha-duplicate-group">';
			html += '<div class="wpha-duplicate-group-header">';
			html +=
				'<h4>' +
				this.escapeHtml(group.filename || 'Unnamed Group') +
				'</h4>';
			html +=
				'<span class="wpha-duplicate-count">' +
				group.items.length +
				' duplicates • ' +
				this.formatBytes(group.total_size) +
				'</span>';
			html += '</div>';
			html += '<div class="wpha-duplicate-items">';

			group.items.forEach((item, index) => {
				const isChecked = this.selectedItems.duplicates.has(item.id);
				const isOriginal = index === 0;
				const previewUrl = item.thumbnail || item.url || '';

				html +=
					'<div class="wpha-duplicate-item" data-id="' +
					item.id +
					'">';
				html += '<div class="wpha-duplicate-item-preview">';
				if (previewUrl) {
					html +=
						'<img class="wpha-lazy-preview" data-src="' +
						previewUrl +
						'" alt="" width="100" height="100" />';
				} else {
					html +=
						'<span class="dashicons dashicons-media-default"></span>';
				}
				html += '</div>';
				html += '<div class="wpha-duplicate-item-info">';
				html +=
					'<p class="wpha-duplicate-filename">' +
					this.escapeHtml(item.filename) +
					'</p>';
				html +=
					'<p class="wpha-duplicate-meta">' +
					this.formatBytes(item.size) +
					' • ' +
					this.formatDate(item.date) +
					'</p>';
				html += '<div class="wpha-duplicate-actions">';
				html +=
					'<input type="checkbox" class="wpha-item-checkbox" data-id="' +
					item.id +
					'" ' +
					(isChecked ? 'checked' : '') +
					' /> ';
				if (isOriginal) {
					html +=
						'<span class="wpha-badge wpha-badge-primary">Original</span>';
				} else {
					html +=
						'<button class="button button-small wpha-keep-original-btn" data-id="' +
						item.id +
						'" data-group="' +
						group.hash +
						'">Keep as Original</button>';
				}
				html += '</div>';
				html += '</div>';
				html += '</div>';
			});

			html += '</div>';
			html += '</div>';
			return html;
		},

		/**
		 * Render pagination.
		 * @param $panel
		 * @param tab
		 * @param totalItems
		 */
		renderPagination($panel, tab, totalItems) {
			const $pagination = $panel.find('.wpha-pagination-controls');
			const $info = $panel.find('.wpha-showing-text');
			const page = this.currentPages[tab];
			const totalPages = Math.ceil(totalItems / this.itemsPerPage);
			const start = (page - 1) * this.itemsPerPage + 1;
			const end = Math.min(page * this.itemsPerPage, totalItems);

			// Update info text
			const unit = tab === 'duplicates' ? 'groups' : 'items';
			$info.text(`Showing ${start}-${end} of ${totalItems} ${unit}`);

			if (totalPages <= 1) {
				$pagination.html('');
				return;
			}

			let html = '';

			// Previous button
			html +=
				'<button class="button wpha-page-btn" data-page="' +
				(page - 1) +
				'" ' +
				(page === 1 ? 'disabled' : '') +
				'>';
			html += '<span class="dashicons dashicons-arrow-left-alt2"></span>';
			html += '</button> ';

			// Page numbers
			const maxButtons = 5;
			let startPage = Math.max(1, page - Math.floor(maxButtons / 2));
			const endPage = Math.min(totalPages, startPage + maxButtons - 1);

			if (endPage - startPage < maxButtons - 1) {
				startPage = Math.max(1, endPage - maxButtons + 1);
			}

			if (startPage > 1) {
				html +=
					'<button class="button wpha-page-btn" data-page="1">1</button> ';
				if (startPage > 2) {
					html += '<span class="wpha-page-dots">...</span> ';
				}
			}

			for (let i = startPage; i <= endPage; i++) {
				const isActive = i === page;
				html +=
					'<button class="button wpha-page-btn' +
					(isActive ? ' button-primary' : '') +
					'" data-page="' +
					i +
					'">' +
					i +
					'</button> ';
			}

			if (endPage < totalPages) {
				if (endPage < totalPages - 1) {
					html += '<span class="wpha-page-dots">...</span> ';
				}
				html +=
					'<button class="button wpha-page-btn" data-page="' +
					totalPages +
					'">' +
					totalPages +
					'</button> ';
			}

			// Next button
			html +=
				'<button class="button wpha-page-btn" data-page="' +
				(page + 1) +
				'" ' +
				(page === totalPages ? 'disabled' : '') +
				'>';
			html +=
				'<span class="dashicons dashicons-arrow-right-alt2"></span>';
			html += '</button>';

			$pagination.html(html);
		},

		/**
		 * Update bulk actions state.
		 * @param $panel
		 * @param tab
		 */
		updateBulkActions($panel, tab) {
			const hasSelection = this.selectedItems[tab].size > 0;
			$panel
				.find('.wpha-bulk-action-select, .wpha-bulk-apply-btn')
				.prop('disabled', !hasSelection);
		},

		/**
		 * Handle rescan.
		 * @param e
		 */
		handleRescan(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);

			if ($btn.prop('disabled')) {
				return;
			}

			$btn.prop('disabled', true).addClass('wpha-loading');
			$btn.find('.dashicons').addClass('wpha-spin');

			wp.apiFetch({
				path: '/wpha/v1/media/scan',
				method: 'POST',
			})
				.then((response) => {
					if (response.success) {
						// Clear cache and reload
						Object.keys(this.dataCache).forEach((key) => {
							if (key !== 'stats') {
								this.dataCache[key] = [];
							}
						});

						this.loadStats();
						this.loadTabData(this.currentTab);

						// Show success message
						this.showNotice(
							'success',
							wpAdminHealthData.i18n.success ||
								'Scan completed successfully.'
						);
					}
				})
				.catch((error) => {
					console.error('Error scanning:', error);
					this.showNotice(
						'error',
						wpAdminHealthData.i18n.error || 'Scan failed.'
					);
				})
				.finally(() => {
					$btn.prop('disabled', false).removeClass('wpha-loading');
					$btn.find('.dashicons').removeClass('wpha-spin');
				});
		},

		/**
		 * Handle sort.
		 * @param e
		 */
		handleSort(e) {
			e.preventDefault();
			const $th = $(e.currentTarget);
			const field = $th.data('sort');
			const tab = this.currentTab;
			const currentSort = this.currentSort[tab];

			// Toggle order if same field, otherwise default to desc
			let order = 'desc';
			if (currentSort.field === field) {
				order = currentSort.order === 'asc' ? 'desc' : 'asc';
			}

			this.currentSort[tab] = { field, order };

			// Update UI
			$th.siblings('.wpha-sortable')
				.removeClass('wpha-sorted-asc wpha-sorted-desc')
				.find('.dashicons')
				.removeClass('dashicons-arrow-up dashicons-arrow-down')
				.addClass('dashicons-sort');

			$th.addClass(
				order === 'asc' ? 'wpha-sorted-asc' : 'wpha-sorted-desc'
			)
				.find('.dashicons')
				.removeClass('dashicons-sort')
				.addClass(
					order === 'asc'
						? 'dashicons-arrow-up'
						: 'dashicons-arrow-down'
				);

			this.renderTabData(tab);
		},

		/**
		 * Handle search filter.
		 * @param e
		 */
		handleSearchFilter(e) {
			const $input = $(e.currentTarget);
			const tab = this.currentTab;
			const value = $input.val().trim();

			this.currentFilters[tab].search = value;
			this.currentPages[tab] = 1; // Reset to first page
			this.renderTabData(tab);
		},

		/**
		 * Handle type filter.
		 * @param e
		 */
		handleTypeFilter(e) {
			const $select = $(e.currentTarget);
			const value = $select.val();

			this.currentFilters.unused.type = value;
			this.currentPages.unused = 1;
			this.renderTabData('unused');
		},

		/**
		 * Handle size filter.
		 * @param e
		 */
		handleSizeFilter(e) {
			const $select = $(e.currentTarget);
			const value = $select.val();

			this.currentFilters['large-files'].size = value;
			this.currentPages['large-files'] = 1;
			this.renderTabData('large-files');
		},

		/**
		 * Handle select all.
		 * @param e
		 */
		handleSelectAll(e) {
			const $checkbox = $(e.currentTarget);
			const isChecked = $checkbox.is(':checked');
			const tab = this.currentTab;
			const $panel = $(`.wpha-tab-panel[data-tab="${tab}"]`);

			$panel
				.find('.wpha-item-checkbox')
				.prop('checked', isChecked)
				.each((i, el) => {
					const id = $(el).data('id');
					if (isChecked) {
						this.selectedItems[tab].add(id);
					} else {
						this.selectedItems[tab].delete(id);
					}
				});

			this.updateBulkActions($panel, tab);
		},

		/**
		 * Handle item select.
		 * @param e
		 */
		handleItemSelect(e) {
			const $checkbox = $(e.currentTarget);
			const id = $checkbox.data('id');
			const isChecked = $checkbox.is(':checked');
			const tab = this.currentTab;
			const $panel = $(`.wpha-tab-panel[data-tab="${tab}"]`);

			if (isChecked) {
				this.selectedItems[tab].add(id);
			} else {
				this.selectedItems[tab].delete(id);
			}

			this.updateBulkActions($panel, tab);
		},

		/**
		 * Handle bulk action.
		 * @param e
		 */
		handleBulkAction(e) {
			e.preventDefault();
			const tab = this.currentTab;
			const $panel = $(`.wpha-tab-panel[data-tab="${tab}"]`);
			const action = $panel.find('.wpha-bulk-action-select').val();
			const items = Array.from(this.selectedItems[tab]);

			if (!action || items.length === 0) {
				return;
			}

			const confirmMsg =
				action === 'delete'
					? 'Are you sure you want to delete ' +
						items.length +
						' item(s)? This action cannot be undone.'
					: 'Are you sure you want to ignore ' +
						items.length +
						' item(s)?';

			if (!confirm(confirmMsg)) {
				return;
			}

			this.executeBulkAction(action, items, tab);
		},

		/**
		 * Execute bulk action.
		 * @param action
		 * @param items
		 * @param tab
		 */
		executeBulkAction(action, items, tab) {
			const apiPath =
				action === 'delete'
					? '/wpha/v1/media/delete'
					: '/wpha/v1/media/ignore';

			wp.apiFetch({
				path: apiPath,
				method: 'POST',
				data: { ids: items },
			})
				.then((response) => {
					if (response.success) {
						// Remove from cache
						this.dataCache[tab] = this.dataCache[tab].filter(
							(item) => !items.includes(item.id)
						);

						// Clear selection
						this.selectedItems[tab].clear();

						// Re-render
						this.renderTabData(tab);
						this.loadStats();

						this.showNotice(
							'success',
							response.data.message ||
								'Action completed successfully.'
						);
					}
				})
				.catch((error) => {
					console.error('Error executing bulk action:', error);
					this.showNotice(
						'error',
						wpAdminHealthData.i18n.error || 'Action failed.'
					);
				});
		},

		/**
		 * Handle delete.
		 * @param e
		 */
		handleDelete(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);
			const id = $btn.data('id');

			if (
				!confirm(
					'Are you sure you want to delete this item? This action cannot be undone.'
				)
			) {
				return;
			}

			this.executeBulkAction('delete', [id], this.currentTab);
		},

		/**
		 * Handle ignore.
		 * @param e
		 */
		handleIgnore(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);
			const id = $btn.data('id');

			this.executeBulkAction('ignore', [id], this.currentTab);
		},

		/**
		 * Handle keep original.
		 * @param e
		 */
		handleKeepOriginal(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);
			const id = $btn.data('id');
			const groupHash = $btn.data('group');

			wp.apiFetch({
				path: '/wpha/v1/media/duplicates/keep-original',
				method: 'POST',
				data: { id, group: groupHash },
			})
				.then((response) => {
					if (response.success) {
						// Reload duplicates
						this.dataCache.duplicates = [];
						this.loadTabData('duplicates');
						this.loadStats();

						this.showNotice(
							'success',
							'Original updated successfully.'
						);
					}
				})
				.catch((error) => {
					console.error('Error updating original:', error);
					this.showNotice(
						'error',
						wpAdminHealthData.i18n.error ||
							'Failed to update original.'
					);
				});
		},

		/**
		 * Handle page change.
		 * @param e
		 */
		handlePageChange(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);

			if ($btn.prop('disabled')) {
				return;
			}

			const page = parseInt($btn.data('page'));
			const tab = this.currentTab;

			this.currentPages[tab] = page;
			this.renderTabData(tab);

			// Scroll to top of results
			$('.wpha-results-section')[0].scrollIntoView({
				behavior: 'smooth',
				block: 'start',
			});
		},

		/**
		 * Lazy load preview images.
		 * @param $container
		 */
		lazyLoadPreviews($container) {
			$container.find('.wpha-lazy-preview').each((i, img) => {
				const $img = $(img);
				const src = $img.data('src');

				if (src) {
					const observer = new IntersectionObserver((entries) => {
						entries.forEach((entry) => {
							if (entry.isIntersecting) {
								$img.attr('src', src);
								observer.unobserve(entry.target);
							}
						});
					});

					observer.observe(img);
				}
			});
		},

		/**
		 * Show empty state.
		 * @param $panel
		 */
		showEmptyState($panel) {
			$panel
				.find('.wpha-tab-panel-content')
				.html(
					'<div class="wpha-empty-state">' +
						(wpAdminHealthData.i18n.no_data ||
							'No data available.') +
						'</div>'
				);
		},

		/**
		 * Show notice.
		 * @param type
		 * @param message
		 */
		showNotice(type, message) {
			const $notice = $(
				'<div class="notice notice-' +
					type +
					' is-dismissible"><p>' +
					message +
					'</p></div>'
			);
			$('.wpha-media-audit-wrap h1').after($notice);

			// Auto dismiss after 5 seconds
			setTimeout(() => {
				$notice.fadeOut(() => $notice.remove());
			}, 5000);
		},

		/**
		 * Format bytes to human readable.
		 * @param bytes
		 */
		formatBytes(bytes) {
			if (bytes === 0) return '0 Bytes';
			const k = 1024;
			const sizes = ['Bytes', 'KB', 'MB', 'GB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));
			return (
				Math.round((bytes / Math.pow(k, i)) * 100) / 100 +
				' ' +
				sizes[i]
			);
		},

		/**
		 * Format date.
		 * @param dateString
		 */
		formatDate(dateString) {
			if (!dateString) return '--';
			const date = new Date(dateString);
			return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
		},

		/**
		 * Escape HTML.
		 * @param text
		 */
		escapeHtml(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;',
			};
			return text.replace(/[&<>"']/g, (m) => map[m]);
		},

		/**
		 * Hide skeleton loaders.
		 * @param $container
		 */
		hideSkeletons($container) {
			$container.find('[class*="-skeleton"]').hide();
		},
	};

	// Initialize on document ready
	$(document).ready(() => {
		if ($('.wpha-media-audit').length) {
			MediaAudit.init();
		}
	});
})(jQuery);
