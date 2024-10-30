<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly      
?>
<div id="cup-footer" class="container mt-5">
	<div class="row">
		<div class="col-sm text-center text-muted">
			<strong><?php esc_html_e( "The Cloud by Cloud Uploads", 'cloud-uploads' ); ?></strong>
		</div>
	</div>
	<div class="row mt-3">
		<div class="col-sm text-center text-muted">
			<a href="<?php echo esc_url( Cloud_Uploads_Admin::api_url( '/support/?utm_source=cloud_uploads_plugin&utm_medium=plugin&utm_campaign=cloud_uploads_plugin&utm_content=footer&utm_term=support' ) ); ?>"
			   class="text-muted text-decoration-none"><?php esc_html_e( "Support", 'cloud-uploads' ); ?></a> |
			<a href="<?php echo esc_url( Cloud_Uploads_Admin::get_instance()->api_url( '/terms-of-service/?utm_source=cloud_uploads_plugin&utm_medium=plugin&utm_campaign=cloud_uploads_plugin&utm_content=footer&utm_term=terms' ) ); ?>"
			   class="text-muted text-decoration-none"><?php esc_html_e( "Terms of Service", 'cloud-uploads' ); ?></a> |
			<a href="<?php echo esc_url( Cloud_Uploads_Admin::get_instance()->api_url( '/privacy/?utm_source=cloud_uploads_plugin&utm_medium=plugin&utm_campaign=cloud_uploads_plugin&utm_content=footer&utm_term=privacy' ) ); ?>"
			   class="text-muted text-decoration-none"><?php esc_html_e( "Privacy Policy", 'cloud-uploads' ); ?></a>
		</div>
	</div>
	<div class="row mt-3">
		<div class="col-sm text-center text-muted">
			<a href="https://twitter.com/cloud-uploads" class="text-muted text-decoration-none" data-toggle="tooltip" title="<?php esc_attr_e( 'Twitter', 'cloud-uploads' ); ?>"><span class="dashicons dashicons-twitter"></span></a>
			<a href="https://www.facebook.com/cloud-uploads/" class="text-muted text-decoration-none" data-toggle="tooltip" title="<?php esc_attr_e( 'Facebook', 'cloud-uploads' ); ?>"><span class="dashicons dashicons-facebook-alt"></span></a>
		</div>
	</div>
</div>
