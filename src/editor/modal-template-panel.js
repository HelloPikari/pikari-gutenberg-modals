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

import { useState } from '@wordpress/element';
import {
	SelectControl,
	Button,
	FlexBlock,
	FlexItem,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Stable layout primitive, used throughout core inspector UI (e.g. the navigation block).
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { plus, pencil } from '@wordpress/icons';
import { useInstanceId } from '@wordpress/compose';
import useModalTemplateEntities from './use-modal-template-entities';
import ModalTemplateCreateModal from './modal-template-create-modal';
import ModalTemplatePreview from './modal-template-preview';
import { createTemplatePartId } from './modal-template-parts';

export default function ModalTemplatePanel( {
	value,
	onChange,
	showCreate = true,
	showPreview = true,
} ) {
	const headingId = useInstanceId(
		ModalTemplatePanel,
		'pikari-modal-template-panel-heading'
	);

	const {
		parts,
		options,
		selectedPart,
		currentTheme,
		isResolving,
		hasResolved,
		isBlockTheme,
	} = useModalTemplateEntities( value );

	const [ isCreating, setIsCreating ] = useState( false );

	// Hybrid themes have no Site Editor and no entities: select only.
	const canCreate = showCreate && isBlockTheme;

	const isEmpty = hasResolved && parts.length === 0;

	const onNavigateToEntityRecord = useSelect(
		( select ) =>
			select( blockEditorStore ).getSettings().onNavigateToEntityRecord,
		[]
	);

	const theme = selectedPart?.theme || currentTheme;

	const onEdit = () => {
		if ( ! selectedPart || ! theme || ! onNavigateToEntityRecord ) {
			return;
		}

		const postId = createTemplatePartId( theme, selectedPart.slug );

		onNavigateToEntityRecord( {
			postId,
			postType: 'wp_template_part',
		} );
	};

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
					onClick={ () => setIsCreating( true ) }
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
							onClick={ () => setIsCreating( true ) }
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
						<FlexItem>
							{ isBlockTheme &&
								selectedPart &&
								hasResolved &&
								onNavigateToEntityRecord && (
								<Button
									__next40pxDefaultSize
									variant="secondary"
									icon={ pencil }
									onClick={ onEdit }
									label={ __(
										'Edit modal template',
										'pikari-gutenberg-modals'
									) }
									showTooltip
								/>
							) }
						</FlexItem>
					</HStack>
				</>
			) }

			{ isCreating && (
				<ModalTemplateCreateModal
					parts={ parts }
					onClose={ () => setIsCreating( false ) }
					onCreated={ ( created ) => {
						setIsCreating( false );
						onChange( created.slug );
					} }
				/>
			) }

			{ showPreview && isBlockTheme && selectedPart && (
				<ModalTemplatePreview slug={ selectedPart.slug } theme={ theme } />
			) }
		</div>
	);
}
