/**
 * The single "Modal" panel shown on every supported trigger block.
 *
 * Supersedes the two near-identical panels in button-modal-extension.js and
 * group-modal-trigger-extension.js.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { isTriggerBlock, hasModalAction } from './trigger-blocks';
import useModalTemplateParts from './use-modal-template-parts';

const withModalPanel = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { name, attributes, setAttributes, isSelected } = props;

		if ( ! isTriggerBlock( name ) ) {
			return <BlockEdit { ...props } />;
		}

		const {
			pikariModalAction,
			pikariModalContentSource,
			pikariModalDirectUrl,
			pikariModalPlacement,
			pikariModalTemplatePart,
			pikariModalAccessibleLabel,
		} = attributes;

		const templateParts = useModalTemplateParts();
		const isOpen = pikariModalAction === 'open';

		return (
			<>
				<BlockEdit { ...props } />
				{ isSelected && (
					<InspectorControls>
						<PanelBody
							title={ __( 'Modal', 'pikari-gutenberg-modals' ) }
							initialOpen={ hasModalAction( attributes ) }
						>
							<SelectControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Action', 'pikari-gutenberg-modals' ) }
								value={ pikariModalAction }
								options={ [
									{ label: __( 'None', 'pikari-gutenberg-modals' ), value: '' },
									{
										label: __( 'Open a modal', 'pikari-gutenberg-modals' ),
										value: 'open',
									},
									{
										label: __( 'Close the modal', 'pikari-gutenberg-modals' ),
										value: 'close',
									},
								] }
								onChange={ ( value ) =>
									setAttributes( { pikariModalAction: value } )
								}
								help={ __(
									'Close is for use inside a modal template part.',
									'pikari-gutenberg-modals'
								) }
							/>

							{ isOpen && (
								<>
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __( 'Content', 'pikari-gutenberg-modals' ) }
										value={ pikariModalContentSource || 'link' }
										options={ [
											{
												label: __( 'Detected link', 'pikari-gutenberg-modals' ),
												value: 'link',
											},
											{
												label: __( 'Custom URL', 'pikari-gutenberg-modals' ),
												value: 'url',
											},
											{
												label: __( 'Inline content', 'pikari-gutenberg-modals' ),
												value: 'inline',
											},
										] }
										onChange={ ( value ) =>
											setAttributes( { pikariModalContentSource: value } )
										}
									/>

									{ pikariModalContentSource === 'url' && (
										<TextControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __( 'URL', 'pikari-gutenberg-modals' ) }
											value={ pikariModalDirectUrl }
											onChange={ ( value ) =>
												setAttributes( { pikariModalDirectUrl: value } )
											}
										/>
									) }

									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __( 'Placement', 'pikari-gutenberg-modals' ) }
										value={ pikariModalPlacement }
										options={ [
											{
												label: __(
													'From the template',
													'pikari-gutenberg-modals'
												),
												value: '',
											},
											{
												label: __( 'Left panel', 'pikari-gutenberg-modals' ),
												value: 'left',
											},
											{
												label: __( 'Right panel', 'pikari-gutenberg-modals' ),
												value: 'right',
											},
										] }
										onChange={ ( value ) =>
											setAttributes( { pikariModalPlacement: value } )
										}
									/>

									{ templateParts.hasMultiple && (
										<SelectControl
											__next40pxDefaultSize
											__nextHasNoMarginBottom
											label={ __( 'Modal template', 'pikari-gutenberg-modals' ) }
											value={ pikariModalTemplatePart }
											options={ templateParts.options }
											onChange={ ( value ) =>
												setAttributes( { pikariModalTemplatePart: value } )
											}
										/>
									) }

									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __( 'Accessible label', 'pikari-gutenberg-modals' ) }
										value={ pikariModalAccessibleLabel }
										onChange={ ( value ) =>
											setAttributes( { pikariModalAccessibleLabel: value } )
										}
										help={ __(
											'Overrides the label announced to screen readers.',
											'pikari-gutenberg-modals'
										) }
									/>
								</>
							) }
						</PanelBody>
					</InspectorControls>
				) }
			</>
		);
	};
}, 'withModalPanel' );

addFilter(
	'editor.BlockEdit',
	'pikari-gutenberg-modals/modal-panel',
	withModalPanel
);
