/**
 * Media Audit Entry Point
 *
 * Main entry point for the media audit admin page.
 * Imports and initializes page-specific functionality.
 *
 * @package
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

// Import React components
import MetricCard from '../components/MetricCard.jsx';

// Import core admin utilities
import '../admin.js';
import '../media-audit.js';

// Make components globally available for use in WordPress templates
window.WPAdminHealthComponents = window.WPAdminHealthComponents || {};
Object.assign(window.WPAdminHealthComponents, {
	MetricCard,
	React,
	createRoot,
});

// Initialize any media audit-specific functionality
document.addEventListener('DOMContentLoaded', () => {
	console.log('Media Audit page loaded and ready');
});
