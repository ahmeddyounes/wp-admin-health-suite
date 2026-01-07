/**
 * Database Health JavaScript
 *
 * Handles database health monitoring, cleanup operations, and table management.
 *
 * @package WPAdminHealth
 */

(function($) {
	'use strict';

	const DatabaseHealth = {
		/**
		 * Initialize the database health module.
		 */
		init() {
			this.cacheDom();
			this.bindEvents();
			this.loadData();
		},

		/**
		 * Cache DOM elements.
		 */
		cacheDom() {
			this.$overviewCards = $('.wpha-overview-cards');
			this.$cleanupAccordion = $('.wpha-cleanup-accordion');
			this.$tableList = $('.wpha-table-list');
		},

		/**
		 * Bind events.
		 */
		bindEvents() {
			$(document).on('click', '.wpha-cleanup-analyze-btn', this.handleAnalyze.bind(this));
			$(document).on('click', '.wpha-cleanup-clean-btn', this.handleClean.bind(this));
			$(document).on('click', '.wpha-accordion-header', this.toggleAccordion.bind(this));
		},

		/**
		 * Load all data.
		 */
		loadData() {
			this.loadOverviewData();
			this.loadCleanupModules();
			this.loadTableList();
		},

		/**
		 * Load overview data.
		 */
		loadOverviewData() {
			wp.apiFetch({
				path: '/wpha/v1/database/stats'
			}).then(response => {
				if (response.success && response.data) {
					this.renderOverviewCards(response.data);
				}
			}).catch(error => {
				console.error('Error loading overview data:', error);
				this.hideSkeletons(this.$overviewCards);
			});
		},

		/**
		 * Render overview cards.
		 */
		renderOverviewCards(data) {
			const dbSize = this.formatBytes(data.database_size);
			const tableCount = data.table_sizes ? data.table_sizes.length : 0;

			// Calculate potential savings
			const potentialSavings =
				(data.revisions_count || 0) * 500 + // Estimate 500 bytes per revision
				(data.expired_transients_count || 0) * 200 + // Estimate 200 bytes per transient
				(data.spam_comments_count || 0) * 300 + // Estimate 300 bytes per spam comment
				(data.trashed_posts_count || 0) * 1000; // Estimate 1KB per trashed post

			const cards = [
				{ value: dbSize, selector: 0 },
				{ value: tableCount.toString(), selector: 1 },
				{ value: this.formatBytes(potentialSavings), selector: 2 }
			];

			cards.forEach(card => {
				const $card = this.$overviewCards.find('.wpha-overview-card').eq(card.selector);
				$card.find('.wpha-overview-value').text(card.value);
				$card.find('.wpha-overview-card-skeleton').hide();
				$card.find('.wpha-overview-card-content').show();
			});
		},

		/**
		 * Load cleanup modules.
		 */
		loadCleanupModules() {
			wp.apiFetch({
				path: '/wpha/v1/database/stats'
			}).then(response => {
				if (response.success && response.data) {
					this.renderCleanupModules(response.data);
				}
			}).catch(error => {
				console.error('Error loading cleanup modules:', error);
				this.hideSkeletons(this.$cleanupAccordion);
			});
		},

		/**
		 * Render cleanup modules.
		 */
		renderCleanupModules(data) {
			const modules = [
				{
					id: 'revisions',
					title: wpAdminHealthData.i18n.revisions || 'Post Revisions',
					icon: 'dashicons-backup',
					count: data.revisions_count || 0,
					size: (data.revisions_count || 0) * 500
				},
				{
					id: 'transients',
					title: wpAdminHealthData.i18n.transients || 'Transients',
					icon: 'dashicons-clock',
					count: data.expired_transients_count || 0,
					size: (data.expired_transients_count || 0) * 200
				},
				{
					id: 'spam',
					title: wpAdminHealthData.i18n.spam || 'Spam Comments',
					icon: 'dashicons-dismiss',
					count: data.spam_comments_count || 0,
					size: (data.spam_comments_count || 0) * 300
				},
				{
					id: 'trash',
					title: wpAdminHealthData.i18n.trash || 'Trash (Posts & Comments)',
					icon: 'dashicons-trash',
					count: (data.trashed_posts_count || 0) + (data.trashed_comments_count || 0),
					size: (data.trashed_posts_count || 0) * 1000 + (data.trashed_comments_count || 0) * 300
				},
				{
					id: 'orphaned',
					title: wpAdminHealthData.i18n.orphaned || 'Orphaned Data',
					icon: 'dashicons-warning',
					count: (data.orphaned_postmeta_count || 0) + (data.orphaned_commentmeta_count || 0) + (data.orphaned_termmeta_count || 0),
					size: 0
				}
			];

			const html = modules.map(module => this.renderModuleHtml(module)).join('');
			this.$cleanupAccordion.html(html);
		},

		/**
		 * Render module HTML.
		 */
		renderModuleHtml(module) {
			return `
				<div class="wpha-accordion-item" data-module="${module.id}">
					<div class="wpha-accordion-header">
						<div class="wpha-accordion-title">
							<span class="dashicons ${module.icon}"></span>
							<span>${module.title}</span>
						</div>
						<div class="wpha-accordion-stats">
							<span class="wpha-item-count">${module.count} items</span>
							${module.size > 0 ? `<span class="wpha-size-estimate">${this.formatBytes(module.size)}</span>` : ''}
						</div>
						<span class="dashicons dashicons-arrow-down-alt2 wpha-accordion-icon"></span>
					</div>
					<div class="wpha-accordion-content">
						<div class="wpha-module-info">
							<p class="wpha-module-description">${this.getModuleDescription(module.id)}</p>
						</div>
						<div class="wpha-module-actions">
							<button class="button wpha-cleanup-analyze-btn" data-module="${module.id}">
								<span class="dashicons dashicons-search"></span>
								${wpAdminHealthData.i18n.analyze || 'Analyze'}
							</button>
							<button class="button button-primary wpha-cleanup-clean-btn" data-module="${module.id}" ${module.count === 0 ? 'disabled' : ''}>
								<span class="dashicons dashicons-trash"></span>
								${wpAdminHealthData.i18n.clean || 'Clean'}
							</button>
						</div>
						<div class="wpha-module-progress" style="display: none;">
							<div class="wpha-progress-bar">
								<div class="wpha-progress-fill" style="width: 0%;"></div>
							</div>
							<p class="wpha-progress-text">${wpAdminHealthData.i18n.processing || 'Processing...'}</p>
						</div>
						<div class="wpha-module-results" style="display: none;"></div>
					</div>
				</div>
			`;
		},

		/**
		 * Get module description.
		 */
		getModuleDescription(moduleId) {
			const descriptions = {
				'revisions': 'Post revisions are automatically saved copies of your content. Cleaning old revisions can free up database space.',
				'transients': 'Transients are temporary cached data. Expired transients are safe to remove.',
				'spam': 'Spam comments are blocked comments that can be safely removed from your database.',
				'trash': 'Trashed posts and comments are items in the trash that can be permanently deleted.',
				'orphaned': 'Orphaned data includes metadata entries with no parent record. This data can be safely removed.'
			};
			return descriptions[moduleId] || '';
		},

		/**
		 * Toggle accordion.
		 */
		toggleAccordion(e) {
			const $header = $(e.currentTarget);
			const $item = $header.closest('.wpha-accordion-item');
			const $content = $item.find('.wpha-accordion-content');
			const isOpen = $item.hasClass('wpha-accordion-open');

			if (isOpen) {
				$item.removeClass('wpha-accordion-open');
				$content.slideUp(200);
			} else {
				$item.addClass('wpha-accordion-open');
				$content.slideDown(200);
			}
		},

		/**
		 * Handle analyze button click.
		 */
		handleAnalyze(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);
			const moduleId = $btn.data('module');

			$btn.prop('disabled', true).addClass('wpha-loading');

			// Load detailed analysis based on module
			let apiPath = '';
			switch(moduleId) {
				case 'revisions':
					apiPath = '/wpha/v1/database/revisions';
					break;
				case 'transients':
					apiPath = '/wpha/v1/database/transients';
					break;
				case 'orphaned':
					apiPath = '/wpha/v1/database/orphaned';
					break;
				default:
					apiPath = '/wpha/v1/database/stats';
			}

			wp.apiFetch({
				path: apiPath
			}).then(response => {
				if (response.success) {
					this.showAnalysisResults($btn.closest('.wpha-accordion-item'), response.data);
				}
			}).catch(error => {
				console.error('Error analyzing:', error);
				alert(wpAdminHealthData.i18n.error || 'An error occurred.');
			}).finally(() => {
				$btn.prop('disabled', false).removeClass('wpha-loading');
			});
		},

		/**
		 * Show analysis results.
		 */
		showAnalysisResults($item, data) {
			const $results = $item.find('.wpha-module-results');
			let html = '<div class="wpha-analysis-results">';
			html += '<h4>Analysis Results</h4>';
			html += '<ul>';

			// Format results based on data structure
			Object.keys(data).forEach(key => {
				if (typeof data[key] === 'number') {
					html += `<li><strong>${this.formatKey(key)}:</strong> ${data[key]}</li>`;
				} else if (typeof data[key] === 'string') {
					html += `<li><strong>${this.formatKey(key)}:</strong> ${data[key]}</li>`;
				}
			});

			html += '</ul></div>';
			$results.html(html).slideDown(200);
		},

		/**
		 * Handle clean button click.
		 */
		handleClean(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);
			const moduleId = $btn.data('module');
			const $item = $btn.closest('.wpha-accordion-item');

			// Confirm before cleaning
			const confirmMsg = wpAdminHealthData.i18n.confirmCleanup ||
				'Are you sure you want to clean this data? This action cannot be undone.';

			if (!confirm(confirmMsg)) {
				return;
			}

			this.executeCleanup($item, moduleId);
		},

		/**
		 * Execute cleanup operation.
		 */
		executeCleanup($item, moduleId) {
			const $progress = $item.find('.wpha-module-progress');
			const $progressFill = $item.find('.wpha-progress-fill');
			const $progressText = $item.find('.wpha-progress-text');
			const $actions = $item.find('.wpha-module-actions');
			const $results = $item.find('.wpha-module-results');

			// Show progress
			$actions.hide();
			$results.hide();
			$progress.show();

			// Simulate progress
			let progress = 0;
			const progressInterval = setInterval(() => {
				progress += Math.random() * 20;
				if (progress > 90) progress = 90;
				$progressFill.css('width', progress + '%');
			}, 200);

			// Execute cleanup via API
			wp.apiFetch({
				path: '/wpha/v1/database/clean',
				method: 'POST',
				data: {
					type: moduleId,
					options: this.getCleanupOptions(moduleId)
				}
			}).then(response => {
				clearInterval(progressInterval);
				$progressFill.css('width', '100%');

				setTimeout(() => {
					$progress.hide();
					if (response.success) {
						this.showCleanupResults($item, response.data);
						// Reload data to update counts
						this.loadData();
					}
				}, 500);
			}).catch(error => {
				clearInterval(progressInterval);
				console.error('Error cleaning:', error);
				$progress.hide();
				$actions.show();
				alert(wpAdminHealthData.i18n.error || 'An error occurred during cleanup.');
			});
		},

		/**
		 * Get cleanup options for module.
		 */
		getCleanupOptions(moduleId) {
			const options = {};

			switch(moduleId) {
				case 'revisions':
					options.keep_per_post = 2; // Keep 2 most recent revisions
					break;
				case 'transients':
					options.expired_only = true;
					break;
				case 'spam':
				case 'trash':
					options.older_than_days = 0; // Clean all
					break;
				case 'orphaned':
					options.types = ['postmeta', 'commentmeta', 'termmeta', 'relationships'];
					break;
			}

			return options;
		},

		/**
		 * Show cleanup results.
		 */
		showCleanupResults($item, data) {
			const $results = $item.find('.wpha-module-results');
			const $actions = $item.find('.wpha-module-actions');

			let html = '<div class="wpha-cleanup-results wpha-success">';
			html += '<h4><span class="dashicons dashicons-yes-alt"></span> Cleanup Completed</h4>';
			html += '<ul>';

			// Format results
			if (data.deleted !== undefined) {
				html += `<li>Items deleted: <strong>${data.deleted}</strong></li>`;
			}
			if (data.posts_deleted !== undefined) {
				html += `<li>Posts deleted: <strong>${data.posts_deleted}</strong></li>`;
			}
			if (data.comments_deleted !== undefined) {
				html += `<li>Comments deleted: <strong>${data.comments_deleted}</strong></li>`;
			}
			if (data.bytes_freed !== undefined) {
				html += `<li>Space freed: <strong>${this.formatBytes(data.bytes_freed)}</strong></li>`;
			}
			if (data.postmeta_deleted !== undefined) {
				html += `<li>Orphaned postmeta deleted: <strong>${data.postmeta_deleted}</strong></li>`;
			}
			if (data.commentmeta_deleted !== undefined) {
				html += `<li>Orphaned commentmeta deleted: <strong>${data.commentmeta_deleted}</strong></li>`;
			}
			if (data.termmeta_deleted !== undefined) {
				html += `<li>Orphaned termmeta deleted: <strong>${data.termmeta_deleted}</strong></li>`;
			}
			if (data.relationships_deleted !== undefined) {
				html += `<li>Orphaned relationships deleted: <strong>${data.relationships_deleted}</strong></li>`;
			}

			html += '</ul></div>';

			$results.html(html).slideDown(200);
			$actions.show();
		},

		/**
		 * Load table list.
		 */
		loadTableList() {
			wp.apiFetch({
				path: '/wpha/v1/database/stats'
			}).then(response => {
				if (response.success && response.data && response.data.table_sizes) {
					this.renderTableList(response.data.table_sizes);
				}
			}).catch(error => {
				console.error('Error loading table list:', error);
				this.hideSkeletons(this.$tableList);
			});
		},

		/**
		 * Render table list.
		 */
		renderTableList(tables) {
			let html = '<table class="wp-list-table widefat fixed striped">';
			html += '<thead><tr>';
			html += '<th>Table Name</th>';
			html += '<th>Rows</th>';
			html += '<th>Data Size</th>';
			html += '<th>Index Size</th>';
			html += '<th>Total Size</th>';
			html += '</tr></thead>';
			html += '<tbody>';

			tables.forEach(table => {
				html += '<tr>';
				html += `<td><strong>${table.name}</strong></td>`;
				html += `<td>${table.rows || 0}</td>`;
				html += `<td>${this.formatBytes(table.data_size || 0)}</td>`;
				html += `<td>${this.formatBytes(table.index_size || 0)}</td>`;
				html += `<td><strong>${this.formatBytes(table.total_size || 0)}</strong></td>`;
				html += '</tr>';
			});

			html += '</tbody></table>';
			this.$tableList.html(html);
		},

		/**
		 * Format bytes to human readable.
		 */
		formatBytes(bytes) {
			if (bytes === 0) return '0 Bytes';
			const k = 1024;
			const sizes = ['Bytes', 'KB', 'MB', 'GB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));
			return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
		},

		/**
		 * Format key to human readable.
		 */
		formatKey(key) {
			return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
		},

		/**
		 * Hide skeleton loaders.
		 */
		hideSkeletons($container) {
			$container.find('[class*="-skeleton"]').hide();
		}
	};

	// Initialize on document ready
	$(document).ready(() => {
		if ($('.wpha-database-health').length) {
			DatabaseHealth.init();
		}
	});

})(jQuery);
