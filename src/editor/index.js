/**
 * WordPress dependencies
 */
import { toggleFormat, applyFormat, removeFormat } from '@wordpress/rich-text';
import './modal-format';
import './modal-trigger-attributes';
import './modal-trigger-panel';
import './modal-trigger-variations';
import './style.scss';

/**
 * Export utility functions for the edit component
 */
export { applyFormat, removeFormat, toggleFormat };
