/**
 * WordPress dependencies
 */
import {
	InspectorControls,
	InnerBlocks,
	useBlockProps,
	MediaUpload,
	MediaUploadCheck,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Stable in WP 6.x, used by core/cover.
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Stable in WP 6.x, used by core/cover.
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	PanelBody,
	FocalPointPicker,
	ToggleControl,
	Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const INNER_BLOCKS_TEMPLATE = [
	[ 'pikari-gutenberg-modals/close-button', {} ],
	[ 'pikari-gutenberg-modals/content-area', {} ],
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		overlayColor,
		backgroundImage,
		focalPoint,
		hasParallax,
	} = attributes;

	const blockProps = useBlockProps();
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	return (
		<>
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					__experimentalIsRenderedInSidebar
					settings={ [
						{
							colorValue: overlayColor,
							label: __(
								'Overlay',
								'pikari-gutenberg-modals'
							),
							onColorChange: ( value ) =>
								setAttributes( {
									overlayColor: value,
								} ),
							isShownByDefault: true,
							enableAlpha: true,
							clearable: true,
							resetAllFilter: () => ( {
								overlayColor: undefined,
							} ),
						},
					] }
					panelId={ clientId }
					{ ...colorGradientSettings }
				/>
			</InspectorControls>

			<InspectorControls>
				<PanelBody
					title={ __(
						'Overlay image',
						'pikari-gutenberg-modals'
					) }
				>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ ( media ) => {
								setAttributes( {
									backgroundImage: {
										url: media.url,
										id: media.id,
										alt: media.alt || '',
									},
								} );
							} }
							allowedTypes={ [ 'image' ] }
							value={ backgroundImage?.id }
							render={ ( { open } ) => (
								<div className="modal-dialog-image-control">
									{ backgroundImage?.url ? (
										<>
											<img
												src={ backgroundImage.url }
												alt={
													backgroundImage.alt ||
													__(
														'Modal background image',
														'pikari-gutenberg-modals'
													)
												}
												style={ {
													width: '100%',
													marginBottom: '8px',
												} }
											/>
											<div>
												<Button
													variant="secondary"
													onClick={ open }
													style={ {
														marginRight: '8px',
													} }
												>
													{ __(
														'Replace Image',
														'pikari-gutenberg-modals'
													) }
												</Button>
												<Button
													variant="link"
													isDestructive
													onClick={ () =>
														setAttributes( {
															backgroundImage:
																undefined,
															focalPoint:
																undefined,
															hasParallax: false,
														} )
													}
												>
													{ __(
														'Remove',
														'pikari-gutenberg-modals'
													) }
												</Button>
											</div>
										</>
									) : (
										<Button
											variant="secondary"
											onClick={ open }
										>
											{ __(
												'Add Background Image',
												'pikari-gutenberg-modals'
											) }
										</Button>
									) }
								</div>
							) }
						/>
					</MediaUploadCheck>
					{ backgroundImage?.url && (
						<>
							<FocalPointPicker
								__nextHasNoMarginBottom
								label={ __(
									'Focal point',
									'pikari-gutenberg-modals'
								) }
								url={ backgroundImage.url }
								value={
									focalPoint || { x: 0.5, y: 0.5 }
								}
								onChange={ ( value ) =>
									setAttributes( { focalPoint: value } )
								}
							/>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Fixed background',
									'pikari-gutenberg-modals'
								) }
								checked={ hasParallax }
								onChange={ ( value ) =>
									setAttributes( {
										hasParallax: value,
									} )
								}
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<InnerBlocks template={ INNER_BLOCKS_TEMPLATE } />
			</div>
		</>
	);
}
