/**
 * Read-only preview of the selected modal template part.
 *
 * Prefers the edited blocks so unsaved Site Editor changes show, falling
 * back to parsing the stored content.
 */

import { useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { parse } from '@wordpress/blocks';
import { BlockPreview } from '@wordpress/block-editor';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createTemplatePartId } from './modal-template-parts';

export default function ModalTemplatePreview( { slug, theme } ) {
	const templatePartId = useMemo(
		() => createTemplatePartId( theme, slug ),
		[ theme, slug ]
	);

	const { content, editedBlocks, hasResolved } = useSelect(
		( select ) => {
			if ( ! templatePartId ) {
				return { content: null, editedBlocks: null, hasResolved: true };
			}

			const { getEditedEntityRecord, hasFinishedResolution } =
				select( coreStore );

			const args = [
				'postType',
				'wp_template_part',
				templatePartId,
				{ context: 'view' },
			];

			const record = getEditedEntityRecord( ...args );

			return {
				content: record?.content,
				editedBlocks: record?.blocks,
				hasResolved: hasFinishedResolution(
					'getEditedEntityRecord',
					args
				),
			};
		},
		[ templatePartId ]
	);

	const blocks = useMemo( () => {
		if ( editedBlocks?.length ) {
			return editedBlocks;
		}

		if ( typeof content === 'string' && content ) {
			return parse( content );
		}

		return [];
	}, [ editedBlocks, content ] );

	if ( ! templatePartId ) {
		return null;
	}

	if ( ! hasResolved ) {
		return (
			<div className="pikari-modal-template-preview is-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div
			className="pikari-modal-template-preview"
			role="region"
			aria-label={ __(
				'Modal template preview',
				'pikari-gutenberg-modals'
			) }
		>
			<BlockPreview.Async
				placeholder={
					<div className="pikari-modal-template-preview__placeholder" />
				}
			>
				<BlockPreview blocks={ blocks } viewportWidth={ 400 } minHeight={ 200 } />
			</BlockPreview.Async>
		</div>
	);
}
