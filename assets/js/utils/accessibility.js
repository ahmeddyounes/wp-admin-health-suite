/**
 * Accessibility Utilities
 *
 * Provides utilities for screen reader announcements, focus management,
 * and other accessibility features.
 *
 * @package
 */

/**
 * Screen Reader Announcer
 *
 * Creates a live region for screen reader announcements
 */
class ScreenReaderAnnouncer {
	constructor() {
		this.liveRegion = null;
		this.init();
	}

	/**
	 * Initialize the live region
	 */
	init() {
		// Check if live region already exists
		this.liveRegion = document.getElementById('wpha-a11y-announcer');

		if (!this.liveRegion) {
			this.liveRegion = document.createElement('div');
			this.liveRegion.id = 'wpha-a11y-announcer';
			this.liveRegion.className = 'screen-reader-text';
			this.liveRegion.setAttribute('aria-live', 'polite');
			this.liveRegion.setAttribute('aria-atomic', 'true');
			this.liveRegion.setAttribute('role', 'status');

			// Add visually hidden styles
			Object.assign(this.liveRegion.style, {
				position: 'absolute',
				left: '-10000px',
				width: '1px',
				height: '1px',
				overflow: 'hidden',
			});

			document.body.appendChild(this.liveRegion);
		}
	}

	/**
	 * Announce a message to screen readers
	 *
	 * @param {string} message  - The message to announce
	 * @param {string} priority - 'polite' or 'assertive'
	 */
	announce(message, priority = 'polite') {
		if (!this.liveRegion) {
			this.init();
		}

		// Update aria-live attribute
		this.liveRegion.setAttribute('aria-live', priority);

		// Clear the live region first to ensure the announcement is made
		this.liveRegion.textContent = '';

		// Use a short delay to ensure screen readers pick up the change
		setTimeout(() => {
			this.liveRegion.textContent = message;
		}, 100);

		// Clear the message after it's been announced
		setTimeout(() => {
			this.liveRegion.textContent = '';
		}, 1000);
	}

	/**
	 * Announce an error message
	 *
	 * @param {string} message - The error message to announce
	 */
	announceError(message) {
		this.announce(message, 'assertive');
	}

	/**
	 * Announce a success message
	 *
	 * @param {string} message - The success message to announce
	 */
	announceSuccess(message) {
		this.announce(message, 'polite');
	}
}

/**
 * Focus Management Utilities
 */
export class FocusManager {
	/**
	 * Set focus to an element with optional scroll behavior
	 *
	 * @param {HTMLElement|string} element - Element or selector
	 * @param {Object}             options - Focus options
	 */
	static setFocus(element, options = {}) {
		const el =
			typeof element === 'string'
				? document.querySelector(element)
				: element;

		if (!el) {
			return;
		}

		// Make element focusable if it's not already
		if (!el.hasAttribute('tabindex')) {
			el.setAttribute('tabindex', '-1');
		}

		// Set focus
		el.focus(options);

		// Optional scroll into view
		if (options.scroll !== false) {
			el.scrollIntoView({
				behavior: 'smooth',
				block: 'center',
				...options.scrollOptions,
			});
		}
	}

	/**
	 * Trap focus within a container (useful for modals)
	 *
	 * @param {HTMLElement} container - The container element
	 * @return {Function} Cleanup function to remove trap
	 */
	static trapFocus(container) {
		if (!container) {
			return () => {};
		}

		const focusableElements = container.querySelectorAll(
			'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
		);

		const firstElement = focusableElements[0];
		const lastElement = focusableElements[focusableElements.length - 1];

		const handleTabKey = (e) => {
			if (e.key !== 'Tab') {
				return;
			}

			// Get active element from container's owner document
			const ownerDoc = container.ownerDocument || document;
			const activeEl = ownerDoc.activeElement;

			if (e.shiftKey) {
				// Shift + Tab
				if (activeEl === firstElement) {
					e.preventDefault();
					lastElement.focus();
				}
			} else if (activeEl === lastElement) {
				// Tab
				e.preventDefault();
				firstElement.focus();
			}
		};

		container.addEventListener('keydown', handleTabKey);

		// Set initial focus
		if (firstElement) {
			firstElement.focus();
		}

		// Return cleanup function
		return () => {
			container.removeEventListener('keydown', handleTabKey);
		};
	}

	/**
	 * Get all focusable elements within a container
	 *
	 * @param {HTMLElement} container - The container element
	 * @return {NodeList} List of focusable elements
	 */
	static getFocusableElements(container) {
		return container.querySelectorAll(
			'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
		);
	}

	/**
	 * Save the currently focused element
	 *
	 * @param {Document} doc - Optional document to get active element from
	 * @return {HTMLElement|null} The currently focused element
	 */
	static saveFocus(doc = document) {
		return doc.activeElement;
	}

	/**
	 * Restore focus to a previously saved element
	 *
	 * @param {HTMLElement} element - The element to restore focus to
	 */
	static restoreFocus(element) {
		if (element && typeof element.focus === 'function') {
			element.focus();
		}
	}
}

/**
 * Keyboard Navigation Utilities
 */
export class KeyboardNav {
	/**
	 * Check if a key event is an activation key (Enter or Space)
	 *
	 * @param {KeyboardEvent} event - The keyboard event
	 * @return {boolean} True if activation key was pressed
	 */
	static isActivationKey(event) {
		return event.key === 'Enter' || event.key === ' ';
	}

	/**
	 * Check if a key event is an escape key
	 *
	 * @param {KeyboardEvent} event - The keyboard event
	 * @return {boolean} True if escape key was pressed
	 */
	static isEscapeKey(event) {
		return event.key === 'Escape' || event.key === 'Esc';
	}

	/**
	 * Check if a key event is an arrow key
	 *
	 * @param {KeyboardEvent} event - The keyboard event
	 * @return {boolean} True if arrow key was pressed
	 */
	static isArrowKey(event) {
		return ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(
			event.key
		);
	}

	/**
	 * Handle roving tabindex for a group of elements
	 *
	 * @param {NodeList|Array} elements     - The elements to manage
	 * @param {number}         currentIndex - The currently active index
	 * @param {KeyboardEvent}  event        - The keyboard event
	 * @return {number} The new active index
	 */
	static rovingTabindex(elements, currentIndex, event) {
		let newIndex = currentIndex;

		if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
			event.preventDefault();
			newIndex = (currentIndex + 1) % elements.length;
		} else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
			event.preventDefault();
			newIndex =
				currentIndex === 0 ? elements.length - 1 : currentIndex - 1;
		} else if (event.key === 'Home') {
			event.preventDefault();
			newIndex = 0;
		} else if (event.key === 'End') {
			event.preventDefault();
			newIndex = elements.length - 1;
		}

		// Update tabindex
		elements.forEach((el, index) => {
			el.setAttribute('tabindex', index === newIndex ? '0' : '-1');
		});

		// Focus the new element
		if (elements[newIndex]) {
			elements[newIndex].focus();
		}

		return newIndex;
	}
}

/**
 * ARIA Utilities
 */
export class AriaUtils {
	/**
	 * Toggle aria-expanded attribute
	 *
	 * @param {HTMLElement} element  - The element to toggle
	 * @param {boolean}     expanded - Optional explicit state
	 * @return {boolean} The new expanded state
	 */
	static toggleExpanded(element, expanded = null) {
		const currentState = element.getAttribute('aria-expanded') === 'true';
		const newState = expanded !== null ? expanded : !currentState;
		element.setAttribute('aria-expanded', String(newState));
		return newState;
	}

	/**
	 * Set aria-hidden on an element and its children
	 *
	 * @param {HTMLElement} element - The element to hide
	 * @param {boolean}     hidden  - Whether to hide or show
	 */
	static setHidden(element, hidden = true) {
		element.setAttribute('aria-hidden', String(hidden));
	}

	/**
	 * Update aria-label
	 *
	 * @param {HTMLElement} element - The element to update
	 * @param {string}      label   - The new label
	 */
	static setLabel(element, label) {
		element.setAttribute('aria-label', label);
	}

	/**
	 * Update aria-describedby
	 *
	 * @param {HTMLElement} element - The element to update
	 * @param {string}      id      - The ID of the describing element
	 */
	static setDescribedBy(element, id) {
		element.setAttribute('aria-describedby', id);
	}

	/**
	 * Update aria-live region
	 *
	 * @param {HTMLElement} element  - The element to update
	 * @param {string}      priority - 'polite', 'assertive', or 'off'
	 */
	static setLive(element, priority = 'polite') {
		element.setAttribute('aria-live', priority);
	}

	/**
	 * Set aria-busy state
	 *
	 * @param {HTMLElement} element - The element to update
	 * @param {boolean}     busy    - Whether the element is busy
	 */
	static setBusy(element, busy = true) {
		element.setAttribute('aria-busy', String(busy));
	}
}

/**
 * Color Contrast Utilities
 */
export class ContrastUtils {
	/**
	 * Calculate relative luminance of a color
	 *
	 * @param {number} r - Red value (0-255)
	 * @param {number} g - Green value (0-255)
	 * @param {number} b - Blue value (0-255)
	 * @return {number} Relative luminance
	 */
	static getLuminance(r, g, b) {
		const [rs, gs, bs] = [r, g, b].map((c) => {
			const val = c / 255;
			return val <= 0.03928
				? val / 12.92
				: Math.pow((val + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
	}

	/**
	 * Calculate contrast ratio between two colors
	 *
	 * @param {string} color1 - First color (hex)
	 * @param {string} color2 - Second color (hex)
	 * @return {number} Contrast ratio
	 */
	static getContrastRatio(color1, color2) {
		const rgb1 = this.hexToRgb(color1);
		const rgb2 = this.hexToRgb(color2);

		if (!rgb1 || !rgb2) {
			return 0;
		}

		const l1 = this.getLuminance(rgb1.r, rgb1.g, rgb1.b);
		const l2 = this.getLuminance(rgb2.r, rgb2.g, rgb2.b);

		const lighter = Math.max(l1, l2);
		const darker = Math.min(l1, l2);

		return (lighter + 0.05) / (darker + 0.05);
	}

	/**
	 * Convert hex color to RGB
	 *
	 * @param {string} hex - Hex color code
	 * @return {Object|null} RGB values
	 */
	static hexToRgb(hex) {
		const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
		return result
			? {
					r: parseInt(result[1], 16),
					g: parseInt(result[2], 16),
					b: parseInt(result[3], 16),
				}
			: null;
	}

	/**
	 * Check if contrast ratio meets WCAG AA standards
	 *
	 * @param {number} ratio - Contrast ratio
	 * @param {string} level - 'normal' or 'large' text
	 * @return {boolean} True if meets standards
	 */
	static meetsWCAG_AA(ratio, level = 'normal') {
		return level === 'large' ? ratio >= 3 : ratio >= 4.5;
	}

	/**
	 * Check if contrast ratio meets WCAG AAA standards
	 *
	 * @param {number} ratio - Contrast ratio
	 * @param {string} level - 'normal' or 'large' text
	 * @return {boolean} True if meets standards
	 */
	static meetsWCAG_AAA(ratio, level = 'normal') {
		return level === 'large' ? ratio >= 4.5 : ratio >= 7;
	}
}

// Create singleton instance of ScreenReaderAnnouncer
const announcer = new ScreenReaderAnnouncer();

// Export the announcer instance
export { announcer };

// Export default announcer methods for convenience
export default {
	announce: (message, priority) => announcer.announce(message, priority),
	announceError: (message) => announcer.announceError(message),
	announceSuccess: (message) => announcer.announceSuccess(message),
};
