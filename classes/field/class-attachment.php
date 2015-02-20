<?php
/**
 * Handles attachment image import and export
 * @todo Implement this
 */
class CIE_Field_Attachment extends CIE_Field_Abstract
{
	public function get_available_fields( array $search = array() )
	{
		return array(
			'attachment_attachments' => __( 'Attachments', 'cie' ),
		);
	}

	public function get_field_values( array $fields, CIE_Element $element )
	{
		if ( ! in_array( 'attachment_attachments', $fields ) ) {
			return array();
		}

		if ( 'attachment' === $element->get_element()->post_type ) {
			return array( array(  $element->get_element()->guid ) );
		}

		foreach ( get_attached_media( $element->get_element(), 'image' ) as $attachment ) {
			$url = wp_get_attachment_url( $attachment );
			if ( $url ) {
				$urls[ $attachment->ID ] = $url;
			}
		}

		return array(
			$urls
		);
	}

	public function set_field_values( array $fields, CIE_Element $element )
	{
		// @todo Implement
		return array();
	}
}