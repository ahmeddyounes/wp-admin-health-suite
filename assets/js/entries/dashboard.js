/**
 * Dashboard Entry Point
 *
 * Main entry point for the dashboard admin page.
 * Imports and initializes React components and page-specific functionality.
 *
 * @package
 */

import React from 'react';
import { createRoot } from 'react-dom/client';

// Import React components
import HealthScoreCircle from '../components/HealthScoreCircle.jsx';
import MetricCard from '../components/MetricCard.jsx';
import ActivityTimeline from '../components/ActivityTimeline.jsx';
import QuickActions from '../components/QuickActions.jsx';
import Recommendations from '../components/Recommendations.jsx';

// Import core admin utilities
import '../admin.js';
import '../charts.js';

// Make components globally available for use in WordPress templates
window.WPAdminHealthComponents = {
	HealthScoreCircle,
	MetricCard,
	ActivityTimeline,
	QuickActions,
	Recommendations,
	React,
	createRoot,
};

// Initialize any dashboard-specific functionality when needed.
