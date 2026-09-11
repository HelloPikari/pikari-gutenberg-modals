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
import { store as coreStore } from '@wordpress/core-data';
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
	headingLevel = 3,
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

	const {
		onNavigateToEntityRecord,
		canCreateTemplatePart,
		canEditSelectedPart,
	} = useSelect(
		( select ) => {
			const { canUser } = select( coreStore );

			return {
				onNavigateToEntityRecord: select( blockEditorStore ).getSettings()
					.onNavigateToEntityRecord,
				// Hybrid themes never show Create/Edit (see canCreate below), so
				// skip the OPTIONS request entirely there -- same reasoning as
				// useModalTemplateEntities()'s `{ enabled: isBlockTheme }`.
				canCreateTemplatePart: isBlockTheme
					? canUser( 'create', {
						kind: 'postType',
						name: 'wp_template_part',
					} )
					: false,
				canEditSelectedPart:
					isBlockTheme && selectedPart?.id
						? canUser( 'update', {
							kind: 'postType',
							name: 'wp_template_part',
							id: selectedPart.id,
						} )
						: false,
			};
		},
		[ isBlockTheme, selectedPart?.id ]
	);

	// Hybrid themes have no Site Editor and no entities: select only.
	// canUser() returns `undefined` while its resolution is in flight, and
	// again briefly whenever selectedPart.id changes; `!!` treats that the
	// same as "no" so the control never renders as usable before we actually
	// know the current user can create template parts. Creating a template
	// part requires edit_theme_options (administrator), while everything
	// else in this panel needs only edit_posts, so on a multi-role site an
	// Editor must never see Create/Edit render, then fail, then disappear.
	const canCreate = showCreate && isBlockTheme && !! canCreateTemplatePart;

	const isEmpty = hasResolved && parts.length === 0;

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

	// Nests under the popover's own <Heading level={ 4 }> when rendered from
	// the inline-format toolbar, so the heading level is a prop rather than a
	// hardcoded <h3> -- a fixed level would make the panel a heading sibling
	// of the popover instead of a subsection of it.
	const HeadingTag = `h${ headingLevel }`;

	return (
		<div className="pikari-modal-template-panel">
			<HeadingTag
				id={ headingId }
				className="pikari-modal-template-panel__heading"
			>
				{ __( 'Modal template', 'pikari-gutenberg-modals' ) }
			</HeadingTag>

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
								disabled={ ! hasResolved || isResolving }
								accessibleWhenDisabled
								help={ helpText }
							/>
						</FlexBlock>
						<FlexItem>
							{ isBlockTheme &&
								selectedPart &&
								hasResolved &&
								onNavigateToEntityRecord &&
								!! canEditSelectedPart && (
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
