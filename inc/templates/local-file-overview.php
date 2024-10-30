<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly      
?>
<div id="local-file-overview-page" class="card">
	<div class="card-header">
		<div class="d-flex align-items-center">
			<h5 class="m-0 mr-auto p-0"><?php esc_html_e( 'Local File Overview', 'cloud-uploads' ); ?></h5>
			<?php require_once( dirname( __FILE__ ) . '/status-icon.php' ); ?>
		</div>
	</div>
	<div class="card-body cloud p-md-5">
		<div class="row align-items-center justify-content-center mb-5">
			<div class="col-lg col-xs-12">
			<p class="lead mb-0"><?php esc_html_e( "Total Bytes / Files", 'cloud-uploads' ); ?></p>
			<span class="h2 text-nowrap"><?php echo esc_html__($stats['local_size']); ?><small class="text-muted"> / <?php echo esc_html__($stats['local_files']); ?></small></span>

			<div class="container p-0 ml-md-3">
					<?php foreach ( $this->get_filetypes( false ) as $type ) { ?>
						<div class="row mt-2">
							<div class="col-1"><span class="badge badge-pill" style="background-color: <?php echo esc_html__($type->color); ?>">&nbsp;</span></div>
							<div class="col-4 lead text-nowrap"><?php echo esc_html__($type->label); ?></div>
							<div class="col-5 text-nowrap"><strong><?php echo esc_html__(size_format( $type->size, 2 )); ?> / <?php echo esc_html__(number_format_i18n( $type->files )); ?></strong></div>
						</div>
					<?php } ?>
					<div class="row mt-2">
						<div class="col text-muted"><small><?php printf( esc_html__( 'Scanned %s ago', 'cloud-uploads' ), esc_html__(human_time_diff( $stats['files_finished'] )) ); ?> &dash; <a href="#" id="run-scan" class="badge badge-primary" data-toggle="modal" data-target="#scan-modal"><span
										data-toggle="tooltip"
										title="<?php esc_attr_e( 'Run a new scan to detect and sync recently uploaded files.', 'cloud-uploads' ); ?>"><?php esc_html_e( 'Refresh', 'cloud-uploads' ); ?></span></a></small>
						</div>
					</div>
				</div>
			</div>
			<div class="col-lg col-xs-12 mt-5 mt-lg-0 text-center cup-pie-wrapper">
				<canvas id="cup-local-pie"></canvas>
			</div>
		</div>
		<div class="row justify-content-center mb-3">
			<div class="col text-center">
				<h4><?php esc_html_e( 'Ready to Connect!', 'cloud-uploads' ); ?></h4>
				<p class="lead"><?php esc_html_e( 'Get smart plan recommendations, create or connect to existing account, and enable video or sync to the cloud.', 'cloud-uploads' ); ?></p>
			</div>
		</div>
		<div class="row justify-content-center mb-5">
			<div class="col text-center">
				<form method="post" action="https://s3uploads.com/connect/?utm_source=cloud_uploads_plugin&utm_medium=plugin&utm_campaign=bfu_plugin&utm_term=connect">
					<input type="hidden" name="action" value="cloud_uploads_connect">
					<input type="hidden" name="site_id" value="<?php echo esc_attr( $this->api->get_site_id() ); ?>">
					<input type="hidden" name="domain" value="<?php echo esc_url( $this->api->network_site_url() ); ?>">
					<input type="hidden" name="redirect_url" value="<?php echo esc_url( $this->settings_url() ); ?>">
					<input type="hidden" name="bytes" value="<?php echo esc_attr( $stats['local_size'] ); ?>">
					<input type="hidden" name="files" value="<?php echo esc_attr( $stats['local_files'] ); ?>">
					<button class="btn text-nowrap btn-primary btn-lg" type="submit"><span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Connect', 'cloud-uploads' ); ?></button>
				</form>
			</div>
		</div>
		<div class="row justify-content-center mb-1">
			<div class="col text-center">
				<img src="<?php echo esc_url( plugins_url( '/assets/img/progress-bar-2.svg', dirname( __FILE__ ) ) ); ?>" alt="Progress steps bar" height="19" width="110"/>
			</div>
		</div>
	</div>
</div>