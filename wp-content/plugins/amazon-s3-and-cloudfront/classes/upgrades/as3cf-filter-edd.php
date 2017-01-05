<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS3CF_Upgrade_EDD_Replace_URLs Class
 *
 * This class handles replacing all S3 URLs in EDD
 * downloads with the local URL.
 *
 * @since 1.2
 */
class AS3CF_Upgrade_EDD_Replace_URLs extends AS3CF_Upgrade {

	/**
	 * @var int
	 */
	protected $upgrade_id = 5;

	/**
	 * @var string
	 */
	protected $upgrade_name = 'replace_edd_urls';

	/**
	 * @var string 'metadata', 'attachment'
	 */
	protected $upgrade_type = 'post meta';

	/**
	 * Get running update text.
	 *
	 * @return string
	 */
	protected function get_running_update_text() {
		return __( 'and ensuring that only the local URL exists in EDD post meta.', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Count attachments to process.
	 *
	 * @return int
	 */
	protected function count_items_to_process() {
		global $wpdb;

		$table_prefixes = $this->as3cf->get_all_blog_table_prefixes();
		$count          = 0;

		foreach ( $table_prefixes as $blog_id => $table_prefix ) {
			$count += (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_prefix}postmeta` WHERE meta_key = 'edd_download_files'" );
		}

		return $count;
	}

	/**
	 * Get items to process.
	 *
	 * @param string     $prefix
	 * @param int        $limit
	 * @param bool|mixed $offset
	 *
	 * @return array
	 */
	protected function get_items_to_process( $prefix, $limit, $offset = false ) {
		global $wpdb;

		$sql = "SELECT * FROM `{$prefix}postmeta` WHERE meta_key = 'edd_download_files'";

		if ( false !== $offset ) {
			$sql .= " AND meta_id > {$offset->meta_id}";
		}

		$sql .= " LIMIT {$limit}";

		return $wpdb->get_results( $sql );
	}

	/**
	 * Upgrade item.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	protected function upgrade_item( $item ) {
		$attachments = maybe_unserialize( $item->meta_value );

		if ( ! is_array( $attachments ) || empty( $attachments ) ) {
			// No attachments to process, return
			return false;
		}

		foreach ( $attachments as $key => $attachment ) {
			if ( ! isset( $attachment['attachment_id'] ) || ! isset( $attachment['file'] ) ) {
				// Can't determine ID or file, continue
				continue;
			}

			if ( $url = $this->as3cf->get_attachment_local_url( $attachment['attachment_id'] ) ) {
				$attachments[ $key ]['file'] = $url;
			}
		}

		update_post_meta( $item->post_id, 'edd_download_files', $attachments );

		return true;
	}

}
