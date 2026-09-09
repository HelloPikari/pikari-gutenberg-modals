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
	RangeControl,
	SelectControl,
	Button,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const INNER_BLOCKS_TEMPLATE = [
	[
		'core/group',
		{
			className: 'modal-chrome',
			style: {
				color: { background: '#ffffff' },
				border: { radius: '20px' },
				spacing: {
					padding: {
						top: '1.5rem',
						right: '1.5rem',
						bottom: '1.5rem',
						left: '1.5rem',
					},
				},
				shadow: '0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06)',
			},
			layout: { type: 'flex', orientation: 'vertical' },
		},
		[
			[
				'core/group',
				{ layout: { type: 'flex', justifyContent: 'right' } },
				[
					[
						'core/button',
						{ text: 'Close', pikariModalAction: 'close' },
					],
				],
			],
			[ 'pikari-gutenberg-modals/content-area', {} ],
		],
	],
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		overlayColor,
		overlayGradient,
		overlayOpacity,
		backgroundImage,
		focalPoint,
		hasParallax,
		placement,
	} = attributes;

	const blockProps = useBlockProps();
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	const hasLegacyChromeStyles = Boolean(
		attributes.backgroundColor ||
		attributes.borderColor ||
		attributes.style?.color?.background ||
		attributes.style?.border ||
		attributes.style?.spacing?.padding ||
		attributes.style?.shadow
	);

	return (
		<>
			{ hasLegacyChromeStyles && (
				<InspectorControls>
					<Notice
						status="warning"
						isDismissible={ false }
						className="modal-dialog-deprecation-notice"
					>
						{ __(
							'Dialog styling (background, border, padding, shadow) should be applied to an inner Group block instead of directly on the Modal Dialog.',
							'pikari-gutenberg-modals'
						) }
					</Notice>
				</InspectorControls>
			) }

			<InspectorControls>
				<PanelBody
					title={ __( 'Placement', 'pikari-gutenberg-modals' ) }
				>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Dialog placement',
							'pikari-gutenberg-modals'
						) }
						value={ placement }
						options={ [
							{
								label: __(
									'Centered',
									'pikari-gutenberg-modals'
								),
								value: '',
							},
							{
								label: __(
									'Left edge',
									'pikari-gutenberg-modals'
								),
								value: 'left',
							},
							{
								label: __(
									'Right edge',
									'pikari-gutenberg-modals'
								),
								value: 'right',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { placement: value } )
						}
						help={ __(
							'Edge placements pin the dialog to the side of the screen at full height. The corners are squared off against the edge.',
							'pikari-gutenberg-modals'
						) }
					/>
				</PanelBody>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					__experimentalIsRenderedInSidebar
					settings={ [
						{
							colorValue: overlayColor,
							gradientValue: overlayGradient,
							label: __(
								'Overlay',
								'pikari-gutenberg-modals'
							),
							onColorChange: ( value ) =>
								setAttributes( {
									overlayColor: value,
								} ),
							onGradientChange: ( value ) =>
								setAttributes( {
									overlayGradient: value,
								} ),
							isShownByDefault: true,
							enableAlpha: true,
							clearable: true,
							resetAllFilter: () => ( {
								overlayColor: undefined,
								overlayGradient: undefined,
							} ),
						},
					] }
					panelId={ clientId }
					{ ...colorGradientSettings }
				/>
			</InspectorControls>

			<InspectorControls>
				<PanelBody
					title={ __( 'Overlay', 'pikari-gutenberg-modals' ) }
				>
					{ /*
					 * Opacity lives here rather than in the colour group: that
					 * group renders inside core's colour ToolsPanel, whose
					 * narrow swatch-oriented column squashes a range control
					 * down to a stub track beside an oversized number input.
					 *
					 * It is a separate attribute from the colour so a theme
					 * with settings.color.custom disabled — where every
					 * available swatch is opaque — can still produce a
					 * translucent backdrop.
					 */ }
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Opacity',
							'pikari-gutenberg-modals'
						) }
						value={ overlayOpacity }
						onChange={ ( value ) =>
							setAttributes( {
								overlayOpacity:
									value === undefined ? 100 : value,
							} )
						}
						min={ 0 }
						max={ 100 }
						step={ 5 }
					/>

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
