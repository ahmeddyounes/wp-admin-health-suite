/**
 * Database Health Entry Point
 *
 * Main entry point for the database health admin page.
 * Imports and initializes page-specific functionality.
 *
 * @package
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

// Import React components
import MetricCard from '../components/MetricCard.jsx';
import ActivityTimeline from '../components/ActivityTimeline.jsx';

// Import core admin utilities
import '../admin.js';
import '../database-health.js';

// Make components globally available for use in WordPress templates
window.WPAdminHealthComponents = window.WPAdminHealthComponents || {};
Object.assign(window.WPAdminHealthComponents, {
	MetricCard,
	ActivityTimeline,
	React,
	createRoot,
});

// Initialize any database health-specific functionality when needed.
