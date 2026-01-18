/**
 * Database Health Entry Point
 *
 * Main entry point for the database health admin page.
 * Imports and initializes page-specific functionality.
 *
 * Components are mounted internally - extensions should use the Extension API
 * rather than directly accessing components.
 *
 * @package
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

// Import React components (internal - not exposed globally)
import MetricCard from '../components/MetricCard.jsx';
import ActivityTimeline from '../components/ActivityTimeline.jsx';

// Import centralized API client
import apiClient, { ApiError } from '../utils/api.js';

// Import extension API
import ExtensionAPI from '../utils/extension-api.js';

// Expose API client globally for IIFE-based scripts (before importing them)
window.WPAdminHealth = window.WPAdminHealth || {};
window.WPAdminHealth.api = apiClient;
window.WPAdminHealth.ApiError = ApiError;

// Expose extension API
window.WPAdminHealth.extensions = ExtensionAPI;

// Import core admin utilities
import '../admin.js';
import '../database-health.js';

/**
 * Internal component registry (not exposed globally)
 * @type {Object}
 */
const Components = {
	MetricCard,
	ActivityTimeline,
};

// Export internal utilities for testing only
export const __testing__ = {
	Components,
	React,
	createRoot,
};
