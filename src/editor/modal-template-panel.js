/**
 * Shared "Modal template" panel.
 *
 * Rendered by every trigger surface. Two states, matching core's navigation
 * overlay selector: a prominent create button when no modal template parts
 * exist, and a select plus a small create button when they do.
 *
 * All branching logic lives in ./modal-template-parts.js so it stays unit
 * testable — the editor packages this file imports are webpack externals and
 * cannot be resolved by Jest.
 */

import {
	SelectControl,
	Button,
	FlexBlock,
	FlexItem,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Stable layout primitive, used throughout core inspector UI (e.g. the navigation block).
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plus } from '@wordpress/icons';
import { useInstanceId } from '@wordpress/compose';
import useModalTemplateEntities from './use-modal-template-entities';

export default function ModalTemplatePanel( {
	value,
	onChange,
	showCreate = true,
	// eslint-disable-next-line no-unused-vars -- consumed by the preview added in Task 9.
	showPreview = true,
} ) {
	const headingId = useInstanceId(
		ModalTemplatePanel,
		'pikari-modal-template-panel-heading'
	);

	const { parts, options, isResolving, hasResolved, isBlockTheme } =
		useModalTemplateEntities( value );

	// Hybrid themes have no Site Editor and no entities: select only.
	const canCreate = showCreate && isBlockTheme;

	const isEmpty = hasResolved && parts.length === 0;

	const helpText = isEmpty
		? __( 'No modal templates found.', 'pikari-gutenberg-modals' )
		: __( 'Select a template for this modal.', 'pikari-gutenberg-modals' );

	return (
		<div className="pikari-modal-template-panel">
			<h3 id={ headingId } className="pikari-modal-template-panel__heading">
				{ __( 'Modal template', 'pikari-gutenberg-modals' ) }
			</h3>

			{ canCreate && isEmpty ? (
				<Button
					__next40pxDefaultSize
					variant="secondary"
					disabled={ isResolving }
					accessibleWhenDisabled
					className="pikari-modal-template-panel__create-prominent"
				>
					{ __( 'Create modal template', 'pikari-gutenberg-modals' ) }
				</Button>
			) : (
				<>
					{ canCreate && (
						<Button
							size="small"
							icon={ plus }
							disabled={ isResolving }
							accessibleWhenDisabled
							label={ __(
								'Create new modal template',
								'pikari-gutenberg-modals'
							) }
							showTooltip
							className="pikari-modal-template-panel__create"
						/>
					) }
					<HStack
						alignment="flex-start"
						className="pikari-modal-template-panel__controls"
					>
						<FlexBlock>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Modal template', 'pikari-gutenberg-modals' ) }
								hideLabelFromVision
								aria-labelledby={ headingId }
								value={ value || '' }
								options={ options }
								onChange={ ( next ) => onChange( next ) }
								disabled={ isResolving }
								accessibleWhenDisabled
								help={ helpText }
							/>
						</FlexBlock>
						<FlexItem>{ /* Edit button added in Task 8. */ }</FlexItem>
					</HStack>
				</>
			) }
		</div>
	);
}
