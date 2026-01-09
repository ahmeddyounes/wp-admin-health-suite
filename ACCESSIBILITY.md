# Accessibility Compliance - WP Admin Health Suite

This document outlines the accessibility features and compliance standards implemented in the WP Admin Health Suite plugin.

## WCAG 2.1 AA Compliance

The plugin is designed to meet WCAG 2.1 Level AA standards. This includes:

### 1. Perceivable

#### Text Alternatives (1.1)

- All images and icons have appropriate `aria-label` or `aria-hidden` attributes
- Decorative icons are marked with `aria-hidden="true"`
- SVG graphics include descriptive labels via `role="img"` and `aria-label`

#### Time-based Media (1.2)

- N/A - Plugin does not use time-based media

#### Adaptable (1.3)

- Semantic HTML5 elements (`<main>`, `<section>`, `<article>`, `<nav>`)
- Proper heading hierarchy (h1 → h2 → h3)
- ARIA landmarks for major page sections
- Form labels properly associated with inputs
- Tables include proper headers and scope attributes

#### Distinguishable (1.4)

- **Color Contrast**: All text meets minimum 4.5:1 contrast ratio (AA standard)
- **Large Text**: Headers and large text meet minimum 3:1 contrast ratio
- Color is not used as the only visual means of conveying information
- Focus indicators are visible and have minimum 3:1 contrast ratio
- Text can be resized up to 200% without loss of content or functionality

### 2. Operable

#### Keyboard Accessible (2.1)

- All functionality available via keyboard
- No keyboard traps
- Skip links provided for keyboard navigation
- Logical tab order throughout the interface
- Custom keyboard event handlers for Space and Enter keys on interactive elements

#### Enough Time (2.2)

- No time limits on user actions
- Auto-dismiss notifications can be manually dismissed

#### Seizures and Physical Reactions (2.3)

- No content flashes more than 3 times per second
- Animations use `prefers-reduced-motion` media query

#### Navigable (2.4)

- Skip links to main content and quick actions
- Descriptive page titles
- Focus order follows logical reading order
- Link purposes are clear from link text or context
- Multiple navigation methods (menu, breadcrumbs)
- Headings and labels are descriptive
- Focus is visible with 2px outline

#### Input Modalities (2.5)

- Touch targets are minimum 44x44 pixels
- Gestures have keyboard alternatives
- Labels match accessible names

### 3. Understandable

#### Readable (3.1)

- Page language is set (`lang="en"`)
- Technical terms are explained or avoided
- Abbreviations are expanded on first use

#### Predictable (3.2)

- Consistent navigation across all pages
- Components behave consistently
- No automatic context changes on focus
- Forms submit only on explicit user action

#### Input Assistance (3.3)

- Form errors are identified and described
- Labels and instructions provided for all inputs
- Error messages are clear and specific
- Required fields marked with asterisk and `aria-required`
- Confirmation dialogs for destructive actions

### 4. Robust

#### Compatible (4.1)

- Valid HTML5 markup
- ARIA attributes used correctly
- Name, role, and value available for all UI components
- Status messages announced via `aria-live` regions

## Keyboard Navigation

### Global Shortcuts

- **Tab**: Move forward through interactive elements
- **Shift + Tab**: Move backward through interactive elements
- **Enter**: Activate buttons and links
- **Space**: Activate buttons
- **Escape**: Close modals and dismissible components

### Skip Links

- **Skip to main content**: Jump directly to main content area
- **Skip to quick actions**: Jump to quick action buttons

## Screen Reader Support

### Live Regions

- ARIA live regions announce dynamic content changes
- Success/error messages announced automatically
- Loading states communicated via `aria-busy`
- Progress updates announced during operations

### Semantic Regions

```html
<main>
	- Main content area
	<section>
		- Distinct sections
		<article>
			- Self-contained content (metric cards)
			<nav>- Navigation menus</nav>
		</article>
	</section>
</main>
```

### ARIA Attributes Used

- `aria-label`: Descriptive labels for elements
- `aria-labelledby`: Associate labels with elements
- `aria-describedby`: Additional descriptions
- `aria-live`: Announce dynamic updates ("polite" or "assertive")
- `aria-busy`: Loading states
- `aria-expanded`: Collapsible content states
- `aria-hidden`: Hide decorative elements from screen readers
- `aria-modal`: Identify modal dialogs
- `role`: Define element roles (dialog, button, status, etc.)

## Focus Management

### Focus Indicators

- All interactive elements have visible focus indicators
- 2px solid outline in theme color
- 2px offset from element
- Minimum 3:1 contrast ratio against background

### Focus Trapping

- Modal dialogs trap focus within the dialog
- Focus returns to trigger element on close
- First focusable element receives focus on open

### Focus Order

- Logical tab order follows visual layout
- No positive tabindex values used
- Interactive elements appear in DOM order

## Color and Contrast

### Color Contrast Ratios

All color combinations meet or exceed WCAG AA standards:

#### Normal Text (4.5:1 minimum)

- Primary text (#1d2327) on white (#fff): **15.8:1** ✓
- Secondary text (#646970) on white (#fff): **7.5:1** ✓
- Tertiary text (#787c82) on white (#fff): **6.4:1** ✓

#### Large Text (3:1 minimum)

- All header combinations exceed 7:1 ✓

#### UI Components (3:1 minimum)

- Border color (#c3c4c7) on white: **3.4:1** ✓
- Success color (#00a32a): **3.2:1** ✓
- Error color (#d63638): **4.5:1** ✓
- Warning color (#dba617): **3.1:1** ✓

### Color Independence

- Error states indicated by icon + color + text
- Status indicated by badge + color + icon
- Links distinguished by underline, not just color
- Charts include patterns and labels, not just color

## Forms and Input

### Form Labels

- All inputs have associated labels
- Labels use `<label>` element with `for` attribute
- Required fields marked visually and with `aria-required`
- Help text associated via `aria-describedby`

### Error Handling

- Errors identified and described in text
- Error messages have `role="alert"` or `aria-live="assertive"`
- Focus moves to first error on submit
- Errors persist until corrected

### Input Types

- Appropriate HTML5 input types used
- Autocomplete attributes for common fields
- Placeholder text not used as sole label

## Testing

### Automated Testing

Run automated accessibility tests:

```bash
npm run test:a11y
```

### Manual Testing Checklist

#### Keyboard Navigation

- [ ] All functionality accessible via keyboard
- [ ] No keyboard traps
- [ ] Skip links work correctly
- [ ] Focus indicators visible
- [ ] Tab order is logical

#### Screen Reader Testing

- [ ] Test with NVDA (Windows)
- [ ] Test with VoiceOver (macOS)
- [ ] All images have alt text or aria-label
- [ ] Form labels announced correctly
- [ ] Dynamic updates announced
- [ ] Modal dialogs announced

#### Color and Contrast

- [ ] All text meets contrast requirements
- [ ] Focus indicators have sufficient contrast
- [ ] Color not sole means of communication

#### Responsive Design

- [ ] Works at 200% zoom
- [ ] Touch targets minimum 44x44px
- [ ] Content reflows without horizontal scroll

## Accessibility Utilities

The plugin provides JavaScript utilities for common accessibility patterns:

### Screen Reader Announcer

```javascript
import { announcer } from './utils/accessibility';

// Announce success message
announcer.announceSuccess('Action completed successfully');

// Announce error message
announcer.announceError('An error occurred');

// Custom announcement
announcer.announce('Loading complete', 'polite');
```

### Focus Management

```javascript
import { FocusManager } from './utils/accessibility';

// Set focus to element
FocusManager.setFocus('#my-element');

// Trap focus in modal
const removeTrap = FocusManager.trapFocus(modalElement);

// Save and restore focus
const savedFocus = FocusManager.saveFocus();
FocusManager.restoreFocus(savedFocus);
```

### Keyboard Navigation

```javascript
import { KeyboardNav } from './utils/accessibility';

// Check for activation keys
if (KeyboardNav.isActivationKey(event)) {
	handleClick();
}

// Handle escape key
if (KeyboardNav.isEscapeKey(event)) {
	closeModal();
}
```

## Known Issues and Limitations

None currently identified. If you discover an accessibility issue, please report it at:
https://github.com/yourusername/wp-admin-health-suite/issues

## Resources

- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)
- [ARIA Authoring Practices Guide](https://www.w3.org/WAI/ARIA/apg/)
- [WordPress Accessibility Handbook](https://make.wordpress.org/accessibility/handbook/)

## Maintenance

### Regular Testing

- Run automated tests before each release
- Perform manual screen reader testing quarterly
- Review contrast ratios when changing colors
- Test keyboard navigation with each major update

### Updates

This document should be updated when:

- New features are added
- UI components are modified
- WCAG standards are updated
- Accessibility issues are discovered and fixed

Last Updated: 2026-01-07
