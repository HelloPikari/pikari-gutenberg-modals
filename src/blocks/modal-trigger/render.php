<?php
/**
 * Server-side rendering of the Modal Trigger block.
 *
 * Adds Interactivity API attributes to the block wrapper and primary link
 * based on the configured content source mode.
 *
 * @package PikariGutenbergModals
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (inner blocks).
 * @var WP_Block $block      Block instance.
 */

use Pikari\GutenbergModals\BlockSupport;
use Pikari\GutenbergModals\GroupModalTriggerSupport;
use Pikari\GutenbergModals\ModalHandler;
use Pikari\GutenbergModals\SpeculativeLoading;

$trigger_action = $attributes['triggerAction'] ?? 'open';
$content_source = $attributes['contentSource'] ?? 'link';
$template_part  = $attributes['templatePart'] ?? '';
$slug           = ! empty( $template_part ) ? $template_part : 'modal';

// Close mode: make the block (or a specific child element) close the modal.
// No asset enqueuing — close triggers exist inside modal template parts
// where assets are already loaded by the opening trigger.
if ( 'close' === $trigger_action ) {
    $primary_link_json = $attributes['primaryLinkId'] ?? '';
    $close_trigger_id  = '';

    // Check if a specific child element was selected as the close trigger.
    if ( ! empty( $primary_link_json ) ) {
        $identifier       = json_decode( $primary_link_json, true );
        $close_trigger_id = $identifier['closeTriggerId'] ?? '';
    }

    if ( ! empty( $close_trigger_id ) ) {
        // Targeted close: add close action to the specific child element by anchor ID.
        $processor = new WP_HTML_Tag_Processor( $content );

        while ( $processor->next_tag() ) {
            if ( $processor->get_attribute( 'id' ) !== $close_trigger_id ) {
                continue;
            }

            $tag_name = strtolower( $processor->get_tag() );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleCloseClick' );
            $processor->set_attribute( 'data-wp-on--keydown', 'actions.handleCloseKeydown' );
            $processor->add_class( 'modal-close-trigger' );

            // <a> tags need role="button" and tabindex when href is removed.
            if ( 'a' === $tag_name ) {
                $processor->remove_attribute( 'href' );
                $processor->set_attribute( 'role', 'button' );
                $processor->set_attribute( 'tabindex', '0' );
                $processor->set_attribute(
                    'aria-label',
                    esc_attr__( 'Close dialog', 'pikari-gutenberg-modals' )
                );
            }

            break;
        }

        // Add data-wp-interactive to wrapper for Interactivity API scope.
        $content   = $processor->get_updated_html();
        $processor = new WP_HTML_Tag_Processor( $content );
        if ( $processor->next_tag() ) {
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
        }

        echo $processor->get_updated_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    } else {
        // Whole-wrapper close: make the entire block the close trigger.
        $processor = new WP_HTML_Tag_Processor( $content );
        if ( $processor->next_tag() ) {
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleCloseClick' );
            $processor->set_attribute( 'data-wp-on--keydown', 'actions.handleCloseKeydown' );
            $processor->set_attribute( 'role', 'button' );
            $processor->set_attribute( 'tabindex', '0' );
            $processor->set_attribute(
                'aria-label',
                esc_attr__( 'Close dialog', 'pikari-gutenberg-modals' )
            );
            $processor->add_class( 'modal-close-trigger' );
        }
        echo $processor->get_updated_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    return;
}

// Open mode: route to the appropriate handler based on content source.
switch ( $content_source ) {
    case 'url':
        $direct_url = $attributes['directUrl'] ?? '';

        if ( empty( $direct_url ) ) {
            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $target_url = esc_url_raw( $direct_url );

        // Validate URL against domain allow/block lists.
        if ( ! ModalHandler::validate_url( $target_url ) ) {
            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        BlockSupport::set_has_modal_triggers( $slug );

        $content_type = 'url';
        $content_id   = $target_url;
        $aria_label   = __( 'Open modal dialog', 'pikari-gutenberg-modals' );

        // Check if URL is internal WordPress content.
        $post_id = url_to_postid( $target_url );
        if ( $post_id > 0 ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $content_type = $post->post_type;
                $content_id   = (string) $post_id;
                SpeculativeLoading::register_modal_post_id( $post_id );

                $post_title = get_the_title( $post );
                if ( ! empty( $post_title ) ) {
                    /* translators: %s: content title */
                    $aria_label = sprintf( __( 'Open %s in modal dialog', 'pikari-gutenberg-modals' ), $post_title );
                }
            }
        }

        $trigger_id = 'modal-trigger-' . wp_unique_id();
        $modal_id   = $content_type . '-' . $content_id;

        $context = [
            'postId'  => $content_id,
            'modalId' => $modal_id,
        ];

        // Mark external URLs so the frontend renders an iframe instead of fetching via REST API.
        if ( $content_type === 'url' ) {
            $context['contentSource'] = 'url';
        }

        $modal_size = $attributes['modalSize'] ?? '';
        if ( ! empty( $modal_size ) ) {
            $context['size'] = $modal_size;
        }

        if ( ! empty( $template_part ) ) {
            $context['templatePart'] = $template_part;
        }

        // Add Interactivity API attributes to the wrapper.
        $processor = new WP_HTML_Tag_Processor( $content );
        if ( $processor->next_tag() ) {
            $processor->set_attribute( 'id', $trigger_id );
            $processor->add_class( 'has-pikari-modal-trigger' );
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute( 'data-wp-context', wp_json_encode( $context ) );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleGroupTriggerClick' );
            $processor->set_attribute( 'data-wp-on--keydown', 'actions.handleTriggerKeydown' );
            $processor->set_attribute( 'data-wp-on--mouseenter', 'actions.handlePrefetchHover' );
            $processor->set_attribute( 'data-wp-on--mouseleave', 'actions.handlePrefetchLeave' );
            $processor->set_attribute( 'role', 'button' );
            $processor->set_attribute( 'tabindex', '0' );
            $processor->set_attribute( 'aria-haspopup', 'dialog' );
            $processor->set_attribute( 'aria-label', esc_attr( $aria_label ) );
        }

        echo $processor->get_updated_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;

    case 'inline':
        $inline_anchor = $attributes['inlineAnchor'] ?? '';

        if ( empty( $inline_anchor ) ) {
            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        BlockSupport::set_has_modal_triggers( $slug );

        $trigger_id = 'modal-trigger-' . wp_unique_id();
        $modal_size = $attributes['modalSize'] ?? '';

        /* translators: %s: modal content identifier */
        $aria_label = sprintf( __( 'Open %s in modal dialog', 'pikari-gutenberg-modals' ), $inline_anchor );

        $context = [
            'contentSource' => 'inline',
            'inlineAnchor'  => $inline_anchor,
            'modalId'       => 'inline-' . $inline_anchor,
        ];

        if ( ! empty( $modal_size ) ) {
            $context['size'] = $modal_size;
        }

        if ( ! empty( $template_part ) ) {
            $context['templatePart'] = $template_part;
        }

        $processor = new WP_HTML_Tag_Processor( $content );
        if ( $processor->next_tag() ) {
            $processor->set_attribute( 'id', $trigger_id );
            $processor->add_class( 'has-pikari-modal-trigger' );
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute( 'data-wp-context', wp_json_encode( $context ) );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleGroupTriggerClick' );
            $processor->set_attribute( 'data-wp-on--keydown', 'actions.handleTriggerKeydown' );
            $processor->set_attribute( 'aria-haspopup', 'dialog' );
            $processor->set_attribute( 'aria-expanded', 'false' );
            $processor->set_attribute( 'data-wp-bind--aria-expanded', 'state.isExpanded' );
            $processor->set_attribute( 'role', 'button' );
            $processor->set_attribute( 'tabindex', '0' );
            $processor->set_attribute( 'aria-label', esc_attr( $aria_label ) );
        }

        echo $processor->get_updated_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;

    case 'link':
    default:
        $primary_link_json = $attributes['primaryLinkId'] ?? '';

        if ( empty( $primary_link_json ) ) {
            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $link_identifier = json_decode( $primary_link_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $link_identifier ) ) {
            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        // Determine matching strategy based on link type.
        $is_post_link = isset( $link_identifier['linkType'] ) && $link_identifier['linkType'] === 'post-link';

        if ( $is_post_link ) {
            $target_block_name = $link_identifier['blockName'] ?? '';

            if ( empty( $target_block_name ) ) {
                echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                return;
            }

            // Find the marked anchor matching our target block type (Phase 2 of two-phase rendering).
            $processor          = new WP_HTML_Tag_Processor( $content );
            $found_primary_link = false;
            $trigger_id         = '';
            $post_id            = null;

            while ( $processor->next_tag( 'a' ) ) {
                if ( ! $processor->has_class( 'pikari-post-link-candidate' ) ) {
                    continue;
                }

                $block_name = $processor->get_attribute( 'data-pikari-block-name' );
                if ( $block_name !== $target_block_name ) {
                    continue;
                }

                $found_primary_link = true;
                $post_id            = $processor->get_attribute( 'data-pikari-post-id' );
                $trigger_id         = 'modal-trigger-link-' . wp_unique_id();

                // Clean up temporary attributes.
                $processor->remove_class( 'pikari-post-link-candidate' );
                $processor->remove_attribute( 'data-pikari-post-id' );
                $processor->remove_attribute( 'data-pikari-block-name' );

                // Add modal attributes.
                $processor->set_attribute( 'id', $trigger_id );
                $processor->set_attribute( 'aria-haspopup', 'dialog' );
                $processor->add_class( 'is-primary-link' );
                $processor->add_class( 'has-pikari-modal' );

                BlockSupport::set_has_modal_triggers( $slug );
                SpeculativeLoading::register_modal_post_id( (int) $post_id );

                break;
            }

            if ( ! $found_primary_link ) {
                echo GroupModalTriggerSupport::cleanup_post_link_markers( $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                return;
            }

            $content = $processor->get_updated_html();
            $content = GroupModalTriggerSupport::cleanup_post_link_markers( $content );

            $modal_size = $attributes['modalSize'] ?? '';

            $context = [
                'postId'  => $post_id,
                'modalId' => 'post-' . $post_id,
            ];

            if ( ! empty( $modal_size ) ) {
                $context['size'] = $modal_size;
            }

            if ( ! empty( $template_part ) ) {
                $context['templatePart'] = $template_part;
            }

            $processor = new WP_HTML_Tag_Processor( $content );
            if ( $processor->next_tag() ) {
                $processor->add_class( 'has-pikari-modal-trigger' );
                $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
                $processor->set_attribute( 'data-wp-context', wp_json_encode( $context ) );
                $processor->set_attribute( 'data-wp-on--click', 'actions.handleGroupTriggerClick' );
                $processor->set_attribute( 'data-wp-on--mouseenter', 'actions.handlePrefetchHover' );
                $processor->set_attribute( 'data-wp-on--mouseleave', 'actions.handlePrefetchLeave' );
                $processor->set_attribute( 'role', 'group' );
                $processor->set_attribute( 'aria-labelledby', $trigger_id );
            }

            echo $processor->get_updated_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        // URL-based matching.
        if ( empty( $link_identifier['linkUrl'] ) ) {
            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $target_url = esc_url_raw( $link_identifier['linkUrl'] );

        $processor          = new WP_HTML_Tag_Processor( $content );
        $found_primary_link = false;
        $trigger_id         = '';
        $content_id         = '';

        while ( $processor->next_tag( 'a' ) ) {
            $href = $processor->get_attribute( 'href' );

            if ( $href === $target_url ) {
                $found_primary_link = true;

                BlockSupport::set_has_modal_triggers( $slug );

                $content_type = 'url';
                $content_id   = $target_url;

                $post_id = url_to_postid( $target_url );
                if ( $post_id > 0 ) {
                    $post = get_post( $post_id );
                    if ( $post ) {
                        $content_type = $post->post_type;
                        $content_id   = (string) $post_id;
                        SpeculativeLoading::register_modal_post_id( $post_id );
                    }
                }

                $trigger_id = 'modal-trigger-link-' . wp_unique_id();
                $modal_id   = $content_type . '-' . $content_id;

                $processor->set_attribute( 'id', $trigger_id );
                $processor->set_attribute( 'aria-haspopup', 'dialog' );
                $processor->add_class( 'is-primary-link' );
                $processor->add_class( 'has-pikari-modal' );

                break;
            }
        }

        if ( ! $found_primary_link ) {
            echo GroupModalTriggerSupport::cleanup_post_link_markers( $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $content = $processor->get_updated_html();
        $content = GroupModalTriggerSupport::cleanup_post_link_markers( $content );

        $modal_size = $attributes['modalSize'] ?? '';

        $context = [
            'postId'  => $content_id,
            'modalId' => $modal_id,
        ];

        if ( $content_type === 'url' ) {
            $context['contentSource'] = 'url';
        }

        if ( ! empty( $modal_size ) ) {
            $context['size'] = $modal_size;
        }

        if ( ! empty( $template_part ) ) {
            $context['templatePart'] = $template_part;
        }

        $processor = new WP_HTML_Tag_Processor( $content );
        if ( $processor->next_tag() ) {
            $processor->add_class( 'has-pikari-modal-trigger' );
            $processor->set_attribute( 'data-wp-interactive', 'pikari-modal' );
            $processor->set_attribute( 'data-wp-context', wp_json_encode( $context ) );
            $processor->set_attribute( 'data-wp-on--click', 'actions.handleGroupTriggerClick' );
            $processor->set_attribute( 'data-wp-on--mouseenter', 'actions.handlePrefetchHover' );
            $processor->set_attribute( 'data-wp-on--mouseleave', 'actions.handlePrefetchLeave' );
            $processor->set_attribute( 'role', 'group' );
            $processor->set_attribute( 'aria-labelledby', $trigger_id );
        }

        echo $processor->get_updated_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
}
