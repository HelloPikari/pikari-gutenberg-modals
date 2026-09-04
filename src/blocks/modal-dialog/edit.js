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
						'pikari-gutenberg-modals/modal-trigger',
						{ triggerAction: 'close' },
						[ [ 'core/button', { text: 'Close' } ] ],
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
				<RangeControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __(
						'Overlay opacity',
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
					help={ __(
						'Set independently of the overlay colour, so a theme that disables custom colours can still produce a translucent backdrop.',
						'pikari-gutenberg-modals'
					) }
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
