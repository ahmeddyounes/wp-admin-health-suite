/**
 * Settings Entry Point
 *
 * Main entry point for the settings admin page.
 * Imports and initializes page-specific functionality.
 *
 * @package
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

// Import core admin utilities
import '../admin.js';

// Make components globally available for use in WordPress templates
window.WPAdminHealthComponents = window.WPAdminHealthComponents || {};
Object.assign(window.WPAdminHealthComponents, {
	React,
	createRoot,
});

// Initialize any settings-specific functionality when needed.
