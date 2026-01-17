/**
 * Tests for Accessibility Utilities
 *
 * @package
 */

import accessibility, {
	announcer,
	FocusManager,
	KeyboardNav,
	AriaUtils,
	ContrastUtils,
} from './accessibility.js';

describe('ScreenReaderAnnouncer', () => {
	let liveRegion;

	beforeEach(() => {
		// Clean up any existing announcer element
		const existing = document.getElementById('wpha-a11y-announcer');
		if (existing) {
			existing.remove();
		}
		// Reset the announcer's internal liveRegion reference
		announcer.liveRegion = null;
	});

	afterEach(() => {
		const existing = document.getElementById('wpha-a11y-announcer');
		if (existing) {
			existing.remove();
		}
		jest.clearAllTimers();
	});

	describe('initialization', () => {
		it('creates live region on first announce', () => {
			announcer.announce('Test message');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion).not.toBeNull();
		});

		it('sets correct accessibility attributes', () => {
			announcer.announce('Test message');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion.getAttribute('aria-atomic')).toBe('true');
			expect(liveRegion.getAttribute('role')).toBe('status');
		});

		it('applies visually hidden styles', () => {
			announcer.announce('Test message');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion.style.position).toBe('absolute');
			expect(liveRegion.style.overflow).toBe('hidden');
		});

		it('has screen-reader-text class', () => {
			announcer.announce('Test message');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion.classList.contains('screen-reader-text')).toBe(
				true
			);
		});

		it('reuses existing live region if present', () => {
			announcer.announce('First message');
			announcer.announce('Second message');

			const regions = document.querySelectorAll('#wpha-a11y-announcer');
			expect(regions.length).toBe(1);
		});
	});

	describe('announce', () => {
		beforeEach(() => {
			jest.useFakeTimers();
		});

		afterEach(() => {
			jest.useRealTimers();
		});

		it('sets polite aria-live by default', () => {
			announcer.announce('Test message');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion.getAttribute('aria-live')).toBe('polite');
		});

		it('sets assertive aria-live when specified', () => {
			announcer.announce('Urgent message', 'assertive');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion.getAttribute('aria-live')).toBe('assertive');
		});

		it('clears content before announcing', () => {
			announcer.announce('Test message');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion.textContent).toBe('');
		});

		it('sets message after delay', () => {
			announcer.announce('Test message');

			liveRegion = document.getElementById('wpha-a11y-announcer');

			jest.advanceTimersByTime(100);
			expect(liveRegion.textContent).toBe('Test message');
		});

		it('clears message after announcement period', () => {
			announcer.announce('Test message');

			liveRegion = document.getElementById('wpha-a11y-announcer');

			jest.advanceTimersByTime(100);
			expect(liveRegion.textContent).toBe('Test message');

			jest.advanceTimersByTime(1000);
			expect(liveRegion.textContent).toBe('');
		});
	});

	describe('announceError', () => {
		it('uses assertive priority', () => {
			announcer.announceError('Error occurred');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion.getAttribute('aria-live')).toBe('assertive');
		});
	});

	describe('announceSuccess', () => {
		it('uses polite priority', () => {
			announcer.announceSuccess('Action completed');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion.getAttribute('aria-live')).toBe('polite');
		});
	});

	describe('default export', () => {
		beforeEach(() => {
			jest.useFakeTimers();
		});

		afterEach(() => {
			jest.useRealTimers();
		});

		it('exposes announce method', () => {
			expect(typeof accessibility.announce).toBe('function');

			accessibility.announce('Test via default');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			jest.advanceTimersByTime(100);
			expect(liveRegion.textContent).toBe('Test via default');
		});

		it('exposes announceError method', () => {
			expect(typeof accessibility.announceError).toBe('function');

			accessibility.announceError('Error via default');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion.getAttribute('aria-live')).toBe('assertive');
		});

		it('exposes announceSuccess method', () => {
			expect(typeof accessibility.announceSuccess).toBe('function');

			accessibility.announceSuccess('Success via default');

			liveRegion = document.getElementById('wpha-a11y-announcer');
			expect(liveRegion.getAttribute('aria-live')).toBe('polite');
		});
	});
});

describe('FocusManager', () => {
	let container;

	beforeEach(() => {
		container = document.createElement('div');
		container.innerHTML = `
			<button id="btn1">Button 1</button>
			<a href="#" id="link1">Link 1</a>
			<input type="text" id="input1" />
			<button id="btn2" disabled>Disabled Button</button>
			<select id="select1"><option>Option</option></select>
			<div id="focusable" tabindex="0">Focusable Div</div>
			<div id="not-focusable">Not Focusable</div>
			<div id="skip-focus" tabindex="-1">Skip Focus</div>
		`;
		document.body.appendChild(container);

		// Mock scrollIntoView for all elements in container
		container.querySelectorAll('*').forEach((el) => {
			el.scrollIntoView = jest.fn();
		});
	});

	afterEach(() => {
		container.remove();
	});

	describe('setFocus', () => {
		it('focuses element by selector', () => {
			FocusManager.setFocus('#btn1');
			expect(document.activeElement.id).toBe('btn1');
		});

		it('focuses element directly', () => {
			const btn = document.getElementById('btn1');
			FocusManager.setFocus(btn);
			expect(document.activeElement.id).toBe('btn1');
		});

		it('adds tabindex to non-focusable elements', () => {
			const div = document.getElementById('not-focusable');
			FocusManager.setFocus(div);
			expect(div.getAttribute('tabindex')).toBe('-1');
		});

		it('does not add tabindex to already focusable elements', () => {
			const div = document.getElementById('focusable');
			FocusManager.setFocus(div);
			expect(div.getAttribute('tabindex')).toBe('0');
		});

		it('handles null element gracefully', () => {
			expect(() => FocusManager.setFocus(null)).not.toThrow();
		});

		it('handles invalid selector gracefully', () => {
			expect(() => FocusManager.setFocus('#nonexistent')).not.toThrow();
		});

		it('calls scrollIntoView by default', () => {
			const btn = document.getElementById('btn1');
			const scrollSpy = jest.spyOn(btn, 'scrollIntoView');

			FocusManager.setFocus(btn);

			expect(scrollSpy).toHaveBeenCalledWith(
				expect.objectContaining({
					behavior: 'smooth',
					block: 'center',
				})
			);
		});

		it('skips scroll when scroll option is false', () => {
			const btn = document.getElementById('btn1');
			const scrollSpy = jest.spyOn(btn, 'scrollIntoView');

			FocusManager.setFocus(btn, { scroll: false });

			expect(scrollSpy).not.toHaveBeenCalled();
		});

		it('respects custom scroll options', () => {
			const btn = document.getElementById('btn1');
			const scrollSpy = jest.spyOn(btn, 'scrollIntoView');

			FocusManager.setFocus(btn, {
				scrollOptions: { block: 'start' },
			});

			expect(scrollSpy).toHaveBeenCalledWith(
				expect.objectContaining({
					block: 'start',
				})
			);
		});
	});

	describe('trapFocus', () => {
		let modalContainer;

		beforeEach(() => {
			modalContainer = document.createElement('div');
			modalContainer.innerHTML = `
				<button id="modal-first">First</button>
				<input type="text" id="modal-input" />
				<button id="modal-last">Last</button>
			`;
			document.body.appendChild(modalContainer);
		});

		afterEach(() => {
			modalContainer.remove();
		});

		it('focuses first element on trap', () => {
			FocusManager.trapFocus(modalContainer);
			expect(document.activeElement.id).toBe('modal-first');
		});

		it('returns cleanup function', () => {
			const cleanup = FocusManager.trapFocus(modalContainer);
			expect(typeof cleanup).toBe('function');
		});

		it('wraps focus from last to first on Tab', () => {
			FocusManager.trapFocus(modalContainer);

			const lastBtn = document.getElementById('modal-last');
			lastBtn.focus();

			const event = new KeyboardEvent('keydown', {
				key: 'Tab',
				bubbles: true,
			});
			const preventDefaultSpy = jest.spyOn(event, 'preventDefault');

			modalContainer.dispatchEvent(event);

			expect(preventDefaultSpy).toHaveBeenCalled();
		});

		it('wraps focus from first to last on Shift+Tab', () => {
			FocusManager.trapFocus(modalContainer);

			const firstBtn = document.getElementById('modal-first');
			firstBtn.focus();

			const event = new KeyboardEvent('keydown', {
				key: 'Tab',
				shiftKey: true,
				bubbles: true,
			});
			const preventDefaultSpy = jest.spyOn(event, 'preventDefault');

			modalContainer.dispatchEvent(event);

			expect(preventDefaultSpy).toHaveBeenCalled();
		});

		it('removes event listener on cleanup', () => {
			const removeEventListenerSpy = jest.spyOn(
				modalContainer,
				'removeEventListener'
			);

			const cleanup = FocusManager.trapFocus(modalContainer);
			cleanup();

			expect(removeEventListenerSpy).toHaveBeenCalledWith(
				'keydown',
				expect.any(Function)
			);
		});

		it('handles null container gracefully', () => {
			const cleanup = FocusManager.trapFocus(null);
			expect(typeof cleanup).toBe('function');
			expect(() => cleanup()).not.toThrow();
		});

		it('ignores non-Tab keys', () => {
			FocusManager.trapFocus(modalContainer);

			const event = new KeyboardEvent('keydown', {
				key: 'Enter',
				bubbles: true,
			});
			const preventDefaultSpy = jest.spyOn(event, 'preventDefault');

			modalContainer.dispatchEvent(event);

			expect(preventDefaultSpy).not.toHaveBeenCalled();
		});
	});

	describe('getFocusableElements', () => {
		it('returns all focusable elements', () => {
			const elements = FocusManager.getFocusableElements(container);

			// btn1, link1, input1, select1, focusable div (but not disabled btn2, not-focusable, skip-focus)
			expect(elements.length).toBe(5);
		});

		it('excludes disabled elements', () => {
			const elements = FocusManager.getFocusableElements(container);
			const ids = Array.from(elements).map((el) => el.id);

			expect(ids).not.toContain('btn2');
		});

		it('excludes tabindex="-1" elements', () => {
			const elements = FocusManager.getFocusableElements(container);
			const ids = Array.from(elements).map((el) => el.id);

			expect(ids).not.toContain('skip-focus');
		});

		it('includes tabindex="0" elements', () => {
			const elements = FocusManager.getFocusableElements(container);
			const ids = Array.from(elements).map((el) => el.id);

			expect(ids).toContain('focusable');
		});
	});

	describe('saveFocus', () => {
		it('returns currently focused element', () => {
			const btn = document.getElementById('btn1');
			btn.focus();

			const saved = FocusManager.saveFocus();
			expect(saved).toBe(btn);
		});

		it('returns body when nothing is focused', () => {
			document.body.focus();
			const saved = FocusManager.saveFocus();
			expect(saved).toBe(document.body);
		});

		it('accepts custom document', () => {
			const btn = document.getElementById('btn1');
			btn.focus();

			const saved = FocusManager.saveFocus(document);
			expect(saved).toBe(btn);
		});
	});

	describe('restoreFocus', () => {
		it('focuses the saved element', () => {
			const btn = document.getElementById('btn1');
			btn.focus();

			const saved = FocusManager.saveFocus();

			// Focus something else
			document.getElementById('input1').focus();

			FocusManager.restoreFocus(saved);
			expect(document.activeElement).toBe(btn);
		});

		it('handles null element gracefully', () => {
			expect(() => FocusManager.restoreFocus(null)).not.toThrow();
		});

		it('handles element without focus method', () => {
			expect(() => FocusManager.restoreFocus({})).not.toThrow();
		});
	});
});

describe('KeyboardNav', () => {
	describe('isActivationKey', () => {
		it('returns true for Enter key', () => {
			const event = new KeyboardEvent('keydown', { key: 'Enter' });
			expect(KeyboardNav.isActivationKey(event)).toBe(true);
		});

		it('returns true for Space key', () => {
			const event = new KeyboardEvent('keydown', { key: ' ' });
			expect(KeyboardNav.isActivationKey(event)).toBe(true);
		});

		it('returns false for other keys', () => {
			const event = new KeyboardEvent('keydown', { key: 'a' });
			expect(KeyboardNav.isActivationKey(event)).toBe(false);
		});
	});

	describe('isEscapeKey', () => {
		it('returns true for Escape key', () => {
			const event = new KeyboardEvent('keydown', { key: 'Escape' });
			expect(KeyboardNav.isEscapeKey(event)).toBe(true);
		});

		it('returns true for Esc key (IE/Edge)', () => {
			const event = new KeyboardEvent('keydown', { key: 'Esc' });
			expect(KeyboardNav.isEscapeKey(event)).toBe(true);
		});

		it('returns false for other keys', () => {
			const event = new KeyboardEvent('keydown', { key: 'Enter' });
			expect(KeyboardNav.isEscapeKey(event)).toBe(false);
		});
	});

	describe('isArrowKey', () => {
		it('returns true for ArrowUp', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowUp' });
			expect(KeyboardNav.isArrowKey(event)).toBe(true);
		});

		it('returns true for ArrowDown', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowDown' });
			expect(KeyboardNav.isArrowKey(event)).toBe(true);
		});

		it('returns true for ArrowLeft', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowLeft' });
			expect(KeyboardNav.isArrowKey(event)).toBe(true);
		});

		it('returns true for ArrowRight', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowRight' });
			expect(KeyboardNav.isArrowKey(event)).toBe(true);
		});

		it('returns false for other keys', () => {
			const event = new KeyboardEvent('keydown', { key: 'Tab' });
			expect(KeyboardNav.isArrowKey(event)).toBe(false);
		});
	});

	describe('rovingTabindex', () => {
		let elements;

		beforeEach(() => {
			const container = document.createElement('div');
			container.innerHTML = `
				<button id="item0" tabindex="0">Item 0</button>
				<button id="item1" tabindex="-1">Item 1</button>
				<button id="item2" tabindex="-1">Item 2</button>
			`;
			document.body.appendChild(container);
			elements = container.querySelectorAll('button');
		});

		afterEach(() => {
			elements[0].parentElement.remove();
		});

		it('moves to next item on ArrowDown', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowDown' });
			const preventDefaultSpy = jest.spyOn(event, 'preventDefault');

			const newIndex = KeyboardNav.rovingTabindex(elements, 0, event);

			expect(newIndex).toBe(1);
			expect(preventDefaultSpy).toHaveBeenCalled();
			expect(elements[0].getAttribute('tabindex')).toBe('-1');
			expect(elements[1].getAttribute('tabindex')).toBe('0');
		});

		it('moves to next item on ArrowRight', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowRight' });

			const newIndex = KeyboardNav.rovingTabindex(elements, 0, event);

			expect(newIndex).toBe(1);
		});

		it('wraps to first item when at last', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowDown' });

			const newIndex = KeyboardNav.rovingTabindex(elements, 2, event);

			expect(newIndex).toBe(0);
		});

		it('moves to previous item on ArrowUp', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowUp' });
			const preventDefaultSpy = jest.spyOn(event, 'preventDefault');

			const newIndex = KeyboardNav.rovingTabindex(elements, 1, event);

			expect(newIndex).toBe(0);
			expect(preventDefaultSpy).toHaveBeenCalled();
		});

		it('moves to previous item on ArrowLeft', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowLeft' });

			const newIndex = KeyboardNav.rovingTabindex(elements, 1, event);

			expect(newIndex).toBe(0);
		});

		it('wraps to last item when at first', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowUp' });

			const newIndex = KeyboardNav.rovingTabindex(elements, 0, event);

			expect(newIndex).toBe(2);
		});

		it('moves to first item on Home', () => {
			const event = new KeyboardEvent('keydown', { key: 'Home' });
			const preventDefaultSpy = jest.spyOn(event, 'preventDefault');

			const newIndex = KeyboardNav.rovingTabindex(elements, 2, event);

			expect(newIndex).toBe(0);
			expect(preventDefaultSpy).toHaveBeenCalled();
		});

		it('moves to last item on End', () => {
			const event = new KeyboardEvent('keydown', { key: 'End' });
			const preventDefaultSpy = jest.spyOn(event, 'preventDefault');

			const newIndex = KeyboardNav.rovingTabindex(elements, 0, event);

			expect(newIndex).toBe(2);
			expect(preventDefaultSpy).toHaveBeenCalled();
		});

		it('focuses the new element', () => {
			const event = new KeyboardEvent('keydown', { key: 'ArrowDown' });

			KeyboardNav.rovingTabindex(elements, 0, event);

			expect(document.activeElement.id).toBe('item1');
		});

		it('does not change index for non-navigation keys', () => {
			const event = new KeyboardEvent('keydown', { key: 'Enter' });

			const newIndex = KeyboardNav.rovingTabindex(elements, 1, event);

			expect(newIndex).toBe(1);
		});
	});
});

describe('AriaUtils', () => {
	let element;

	beforeEach(() => {
		element = document.createElement('button');
		document.body.appendChild(element);
	});

	afterEach(() => {
		element.remove();
	});

	describe('toggleExpanded', () => {
		it('toggles from false to true', () => {
			element.setAttribute('aria-expanded', 'false');

			const result = AriaUtils.toggleExpanded(element);

			expect(result).toBe(true);
			expect(element.getAttribute('aria-expanded')).toBe('true');
		});

		it('toggles from true to false', () => {
			element.setAttribute('aria-expanded', 'true');

			const result = AriaUtils.toggleExpanded(element);

			expect(result).toBe(false);
			expect(element.getAttribute('aria-expanded')).toBe('false');
		});

		it('sets explicit state when provided', () => {
			element.setAttribute('aria-expanded', 'false');

			const result = AriaUtils.toggleExpanded(element, true);

			expect(result).toBe(true);
			expect(element.getAttribute('aria-expanded')).toBe('true');
		});

		it('treats missing attribute as false', () => {
			const result = AriaUtils.toggleExpanded(element);

			expect(result).toBe(true);
			expect(element.getAttribute('aria-expanded')).toBe('true');
		});
	});

	describe('setHidden', () => {
		it('sets aria-hidden to true by default', () => {
			AriaUtils.setHidden(element);

			expect(element.getAttribute('aria-hidden')).toBe('true');
		});

		it('sets aria-hidden to false when specified', () => {
			AriaUtils.setHidden(element, false);

			expect(element.getAttribute('aria-hidden')).toBe('false');
		});
	});

	describe('setLabel', () => {
		it('sets aria-label attribute', () => {
			AriaUtils.setLabel(element, 'Test Label');

			expect(element.getAttribute('aria-label')).toBe('Test Label');
		});
	});

	describe('setDescribedBy', () => {
		it('sets aria-describedby attribute', () => {
			AriaUtils.setDescribedBy(element, 'description-id');

			expect(element.getAttribute('aria-describedby')).toBe(
				'description-id'
			);
		});
	});

	describe('setLive', () => {
		it('sets aria-live to polite by default', () => {
			AriaUtils.setLive(element);

			expect(element.getAttribute('aria-live')).toBe('polite');
		});

		it('sets aria-live to assertive when specified', () => {
			AriaUtils.setLive(element, 'assertive');

			expect(element.getAttribute('aria-live')).toBe('assertive');
		});

		it('sets aria-live to off when specified', () => {
			AriaUtils.setLive(element, 'off');

			expect(element.getAttribute('aria-live')).toBe('off');
		});
	});

	describe('setBusy', () => {
		it('sets aria-busy to true by default', () => {
			AriaUtils.setBusy(element);

			expect(element.getAttribute('aria-busy')).toBe('true');
		});

		it('sets aria-busy to false when specified', () => {
			AriaUtils.setBusy(element, false);

			expect(element.getAttribute('aria-busy')).toBe('false');
		});
	});
});

describe('ContrastUtils', () => {
	describe('hexToRgb', () => {
		it('converts hex color to RGB object', () => {
			const result = ContrastUtils.hexToRgb('#ff0000');

			expect(result).toEqual({ r: 255, g: 0, b: 0 });
		});

		it('handles lowercase hex', () => {
			const result = ContrastUtils.hexToRgb('#00ff00');

			expect(result).toEqual({ r: 0, g: 255, b: 0 });
		});

		it('handles uppercase hex', () => {
			const result = ContrastUtils.hexToRgb('#0000FF');

			expect(result).toEqual({ r: 0, g: 0, b: 255 });
		});

		it('handles hex without hash', () => {
			const result = ContrastUtils.hexToRgb('ffffff');

			expect(result).toEqual({ r: 255, g: 255, b: 255 });
		});

		it('returns null for invalid hex', () => {
			expect(ContrastUtils.hexToRgb('#ff')).toBeNull();
			expect(ContrastUtils.hexToRgb('invalid')).toBeNull();
			expect(ContrastUtils.hexToRgb('')).toBeNull();
		});
	});

	describe('getLuminance', () => {
		it('calculates luminance for black', () => {
			const luminance = ContrastUtils.getLuminance(0, 0, 0);
			expect(luminance).toBe(0);
		});

		it('calculates luminance for white', () => {
			const luminance = ContrastUtils.getLuminance(255, 255, 255);
			expect(luminance).toBeCloseTo(1, 5);
		});

		it('calculates correct luminance for mid-gray', () => {
			const luminance = ContrastUtils.getLuminance(128, 128, 128);
			expect(luminance).toBeGreaterThan(0);
			expect(luminance).toBeLessThan(1);
		});

		it('weights RGB channels correctly', () => {
			// Green should have highest luminance contribution
			const redLuminance = ContrastUtils.getLuminance(255, 0, 0);
			const greenLuminance = ContrastUtils.getLuminance(0, 255, 0);
			const blueLuminance = ContrastUtils.getLuminance(0, 0, 255);

			expect(greenLuminance).toBeGreaterThan(redLuminance);
			expect(redLuminance).toBeGreaterThan(blueLuminance);
		});
	});

	describe('getContrastRatio', () => {
		it('calculates correct ratio for black and white', () => {
			const ratio = ContrastUtils.getContrastRatio('#000000', '#ffffff');
			expect(ratio).toBeCloseTo(21, 0);
		});

		it('calculates correct ratio for same colors', () => {
			const ratio = ContrastUtils.getContrastRatio('#ff0000', '#ff0000');
			expect(ratio).toBe(1);
		});

		it('returns same ratio regardless of color order', () => {
			const ratio1 = ContrastUtils.getContrastRatio('#000000', '#ffffff');
			const ratio2 = ContrastUtils.getContrastRatio('#ffffff', '#000000');

			expect(ratio1).toBe(ratio2);
		});

		it('returns 0 for invalid colors', () => {
			expect(ContrastUtils.getContrastRatio('#invalid', '#ffffff')).toBe(
				0
			);
			expect(ContrastUtils.getContrastRatio('#000000', '#invalid')).toBe(
				0
			);
		});
	});

	describe('meetsWCAG_AA', () => {
		it('returns true for ratio >= 4.5 with normal text', () => {
			expect(ContrastUtils.meetsWCAG_AA(4.5)).toBe(true);
			expect(ContrastUtils.meetsWCAG_AA(5)).toBe(true);
			expect(ContrastUtils.meetsWCAG_AA(21)).toBe(true);
		});

		it('returns false for ratio < 4.5 with normal text', () => {
			expect(ContrastUtils.meetsWCAG_AA(4.4)).toBe(false);
			expect(ContrastUtils.meetsWCAG_AA(3)).toBe(false);
			expect(ContrastUtils.meetsWCAG_AA(1)).toBe(false);
		});

		it('returns true for ratio >= 3 with large text', () => {
			expect(ContrastUtils.meetsWCAG_AA(3, 'large')).toBe(true);
			expect(ContrastUtils.meetsWCAG_AA(4, 'large')).toBe(true);
		});

		it('returns false for ratio < 3 with large text', () => {
			expect(ContrastUtils.meetsWCAG_AA(2.9, 'large')).toBe(false);
			expect(ContrastUtils.meetsWCAG_AA(2, 'large')).toBe(false);
		});
	});

	describe('meetsWCAG_AAA', () => {
		it('returns true for ratio >= 7 with normal text', () => {
			expect(ContrastUtils.meetsWCAG_AAA(7)).toBe(true);
			expect(ContrastUtils.meetsWCAG_AAA(10)).toBe(true);
			expect(ContrastUtils.meetsWCAG_AAA(21)).toBe(true);
		});

		it('returns false for ratio < 7 with normal text', () => {
			expect(ContrastUtils.meetsWCAG_AAA(6.9)).toBe(false);
			expect(ContrastUtils.meetsWCAG_AAA(4.5)).toBe(false);
		});

		it('returns true for ratio >= 4.5 with large text', () => {
			expect(ContrastUtils.meetsWCAG_AAA(4.5, 'large')).toBe(true);
			expect(ContrastUtils.meetsWCAG_AAA(7, 'large')).toBe(true);
		});

		it('returns false for ratio < 4.5 with large text', () => {
			expect(ContrastUtils.meetsWCAG_AAA(4.4, 'large')).toBe(false);
			expect(ContrastUtils.meetsWCAG_AAA(3, 'large')).toBe(false);
		});
	});

	describe('WCAG compliance integration', () => {
		it('black on white passes all standards', () => {
			const ratio = ContrastUtils.getContrastRatio('#000000', '#ffffff');

			expect(ContrastUtils.meetsWCAG_AA(ratio)).toBe(true);
			expect(ContrastUtils.meetsWCAG_AAA(ratio)).toBe(true);
		});

		it('light gray on white may fail AA', () => {
			const ratio = ContrastUtils.getContrastRatio('#cccccc', '#ffffff');

			expect(ContrastUtils.meetsWCAG_AA(ratio)).toBe(false);
		});
	});
});
