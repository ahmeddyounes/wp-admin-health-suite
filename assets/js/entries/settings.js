/**
 * Settings Entry Point
 *
 * Main entry point for the settings admin page.
 * Imports and initializes page-specific functionality.
 *
 * Components are mounted internally - extensions should use the Extension API
 * rather than directly accessing components.
 *
 * @package
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

// Import extension API
import ExtensionAPI from '../utils/extension-api.js';

// Import core admin utilities
import '../admin.js';

// Expose extension API
window.WPAdminHealth = window.WPAdminHealth || {};
window.WPAdminHealth.extensions = ExtensionAPI;

// Export internal utilities for testing only
export const __testing__ = {
	React,
	createRoot,
};
