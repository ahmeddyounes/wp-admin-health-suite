/**
 * Performance Entry Point
 *
 * Main entry point for the performance admin page.
 * Imports and initializes page-specific functionality.
 *
 * @package WPAdminHealth
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

// Import React components
import MetricCard from '../components/MetricCard.jsx';
import Recommendations from '../components/Recommendations.jsx';

// Import core admin utilities
import '../admin.js';
import '../charts.js';
import '../performance.js';

// Make components globally available for use in WordPress templates
window.WPAdminHealthComponents = window.WPAdminHealthComponents || {};
Object.assign(window.WPAdminHealthComponents, {
	MetricCard,
	Recommendations,
	React,
	createRoot,
});

// Initialize any performance-specific functionality
document.addEventListener('DOMContentLoaded', () => {
	console.log('Performance page loaded and ready');
});
