/**
 * Create-modal-template dialog: pick a starter pattern, name it, create it.
 */

import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { parse } from '@wordpress/blocks';
import { BlockPreview } from '@wordpress/block-editor';
import {
	Modal,
	Button,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Stable layout primitive, used throughout core inspector UI (e.g. the navigation block).
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { selectModalPatterns } from './modal-template-parts';
import useCreateModalTemplate from './use-create-modal-template';

export default function ModalTemplateCreateModal( {
	parts,
	onClose,
	onCreated,
} ) {
	const patterns = useSelect(
		( select ) =>
			selectModalPatterns( select( coreStore ).getBlockPatterns() ),
		[]
	);

	// getBlockPatterns() is a resolved selector: patterns can still be an
	// empty array on the render that mounts this modal. Seeding useState
	// from patterns[ 0 ] would then latch onto '' and never recover once
	// patterns arrive, so the first pattern is treated as the fallback
	// selection rather than the initial state.
	const [ selectedName, setSelectedName ] = useState( '' );
	const [ title, setTitle ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );

	const createModalTemplate = useCreateModalTemplate( parts );
	const effectiveName = selectedName || patterns[ 0 ]?.name || '';
	const selectedPattern = patterns.find(
		( pattern ) => pattern.name === effectiveName
	);

	const onSubmit = async ( event ) => {
		event.preventDefault();
		setIsBusy( true );

		try {
			const created = await createModalTemplate( {
				title: title || __( 'Modal', 'pikari-gutenberg-modals' ),
				patternContent: selectedPattern?.content,
			} );
			onCreated( created );
		} finally {
			setIsBusy( false );
		}
	};

	return (
		<Modal
			title={ __( 'Create modal template', 'pikari-gutenberg-modals' ) }
			onRequestClose={ onClose }
		>
			<form onSubmit={ onSubmit }>
				<div className="pikari-modal-template-create__patterns">
					{ patterns.map( ( pattern ) => (
						<button
							key={ pattern.name }
							type="button"
							className={ `pikari-modal-template-create__pattern${
								pattern.name === effectiveName ? ' is-selected' : ''
							}` }
							aria-pressed={ pattern.name === effectiveName }
							onClick={ () => setSelectedName( pattern.name ) }
						>
							<BlockPreview
								blocks={ parse( pattern.content ) }
								viewportWidth={ 800 }
							/>
							<span>{ pattern.title }</span>
						</button>
					) ) }
				</div>

				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Name', 'pikari-gutenberg-modals' ) }
					value={ title }
					onChange={ setTitle }
				/>

				<HStack justify="right">
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'pikari-gutenberg-modals' ) }
					</Button>
					<Button
						variant="primary"
						type="submit"
						isBusy={ isBusy }
						disabled={ isBusy || ! effectiveName }
						accessibleWhenDisabled
					>
						{ __( 'Create', 'pikari-gutenberg-modals' ) }
					</Button>
				</HStack>
			</form>
		</Modal>
	);
}
