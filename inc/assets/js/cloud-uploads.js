jQuery(document).ready(function ($) {
	$('[data-toggle="tooltip"]').tooltip();
	$('.color-field').wpColorPicker();

	var cupStopLoop = false;
	var cupProcessingLoop = false;
	var cupLoopErrors = 0;
	var cupAjaxCall = false;

	//show a confirmation warning if leaving page during a bulk action
	$(window).on("unload", function () {
		if (cupProcessingLoop) {
			return cloud_uploads_data.strings.leave_confirmation;
		}
	});

	//show an error at top of main settings page
	var showError = function (error_message) {
		if(error_message) {
			$('#cup-error').text(error_message.substr(0, 200)).show();
			$('html, body').animate({scrollTop: 0}, 1000);
		} else {
			$('#cup-error').text('Some error occurred').show();
			$('html, body').animate({scrollTop: 0}, 1000);
		}
	};

	var buildFilelist = function (remaining_dirs, nonce = '') {
		if (cupStopLoop) {
			cupStopLoop = false;
			cupProcessingLoop = false;
			return false;
		}
		cupProcessingLoop = true;

		var data = {remaining_dirs: remaining_dirs};
		if (nonce) {
			data.nonce = nonce;
		} else {
			data.nonce = cloud_uploads_data.nonce.scan;
		}
		$.post(
			ajaxurl + '?action=cloud-uploads-filelist',
			data,
			function (json) {
				if (json.success) {
					console.log('response is ', json);
					$('#cup-scan-storage').text(json.data.local_size);
					$('#cup-scan-files').text(json.data.local_files);
					$('#cup-scan-progress').show();
					if (!json.data.is_done) {
						buildFilelist(
							json.data.remaining_dirs,
							json.data.nonce
						);
					} else {
						cupProcessingLoop = false;
						location.reload();
						return true;
					}
				} else {
					showError(json.data);
					$('.modal').modal('hide');
				}
			},
			'json'
		).fail(function () {
			showError(cloud_uploads_data.strings.ajax_error);
			$('.modal').modal('hide');
		});
	};

	var fetchRemoteFilelist = function (next_token, nonce = '') {
		if (cupStopLoop) {
			cupStopLoop = false;
			cupProcessingLoop = false;
			return false;
		}
		cupProcessingLoop = true;

		var data = {next_token: next_token};
		if (nonce) {
			data.nonce = nonce;
		} else {
			data.nonce = cloud_uploads_data.nonce.scan;
		}
		$.post(
			ajaxurl + '?action=cloud-uploads-remote-filelist',
			data,
			function (json) {
				if (json.success) {
					$('#cup-scan-remote-storage').text(
						json.data.cloud_size
					);
					$('#cup-scan-remote-files').text(json.data.cloud_files);
					$('#cup-scan-remote-progress').show();
					if (!json.data.is_done) {
						fetchRemoteFilelist(
							json.data.next_token,
							json.data.nonce
						);
					} else {
						if ('upload' === window.cupNextStep) {
							//update values in next modal
							$('#cup-progress-size').text(
								json.data.remaining_size
							);
							$('#cup-progress-files').text(
								json.data.remaining_files
							);
							if ('0' == json.data.remaining_files) {
								$('#cup-upload-progress').hide();
							} else {
								$('#cup-upload-progress').show();
							}
							$('#cup-sync-progress-bar')
								.css('width', json.data.pcnt_complete + '%')
								.attr(
									'aria-valuenow',
									json.data.pcnt_complete
								)
								.text(json.data.pcnt_complete + '%');

							$('#cup-sync-button').attr(
								'data-target',
								'#upload-modal'
							);
							$('.modal').modal('hide');
							$('#upload-modal').modal('show');
						} else if ('download' === window.cupNextStep) {
							$('.modal').modal('hide');
							$('#download-modal').modal('show');
						} else {
							location.reload();
						}
					}
				} else {
					showError(json.data);
					$('.modal').modal('hide');
				}
			},
			'json'
		).fail(function () {
			showError(cloud_uploads_data.strings.ajax_error);
			$('.modal').modal('hide');
		});
	};

	var syncFilelist = function (nonce = '') {
		if (cupStopLoop) {
			cupStopLoop = false;
			cupProcessingLoop = false;
			return false;
		}
		cupProcessingLoop = true;

		var data = {};
		if (nonce) {
			data.nonce = nonce;
		} else {
			data.nonce = cloud_uploads_data.nonce.sync;
		}
		cupAjaxCall = $.post(
			ajaxurl + '?action=cloud-uploads-sync',
			data,
			function (json) {
				cupLoopErrors = 0;
				if (json.success) {
					//$('.cup-progress-pcnt').text(json.data.pcnt_complete);
					$('#cup-progress-size').text(json.data.remaining_size);
					$('#cup-progress-files').text(
						json.data.remaining_files
					);
					$('#cup-upload-progress').show();
					$('#cup-sync-progress-bar')
						.css('width', json.data.pcnt_complete + '%')
						.attr('aria-valuenow', json.data.pcnt_complete)
						.text(json.data.pcnt_complete + '%');
					if (!json.data.is_done) {
						data.nonce = json.data.nonce; //save for future errors
						syncFilelist(json.data.nonce);
					} else {
						cupStopLoop = true;
						$('#cup-upload-progress').hide();
						//update values in next modal
						$('#cup-enable-errors span').text(
							json.data.permanent_errors
						);
						if (json.data.permanent_errors) {
							$('.cup-enable-errors').show();
						}
						$('#cup-sync-button').attr(
							'data-target',
							'#enable-modal'
						);
						$('.modal').modal('hide');
						$('#enable-modal').modal('show');
					}
					if (
						Array.isArray(json.data.errors) &&
						json.data.errors.length
					) {
						$.each(json.data.errors, function (i, value) {
							$('#cup-sync-errors ul').append(
								'<li><span class="dashicons dashicons-warning"></span> ' +
								value +
								'</li>'
							);
						});
						$('#cup-sync-errors').show();
						var scroll = $('#cup-sync-errors')[0].scrollHeight;
						$('#cup-sync-errors').animate(
							{scrollTop: scroll},
							5000
						);
					}
				} else {
					showError(json.data);
					$('.modal').modal('hide');
				}
			},
			'json'
		).fail(function () {
			//if we get an error like 504 try up to 6 times with an exponential backoff to let the server cool down before giving up.
			cupLoopErrors++;
			if (cupLoopErrors > 6) {
				showError(cloud_uploads_data.strings.ajax_error);
				$('.modal').modal('hide');
				cupLoopErrors = 0;
				cupProcessingLoop = false;
			} else {
				var exponentialBackoff = Math.floor(
					Math.pow(cupLoopErrors, 2.5) * 1000
				); //max 90s
				console.log(
					'Server error. Waiting ' +
					exponentialBackoff +
					'ms before retrying'
				);
				setTimeout(function () {
					syncFilelist(data.nonce);
				}, exponentialBackoff);
			}
		});
	};

	var getSyncStatus = function () {
		if (!cupProcessingLoop) {
			return false;
		}

		$.get(
			ajaxurl + '?action=cloud-uploads-status',
			function (json) {
				if (json.success) {
					$('#cup-progress-size').text(json.data.remaining_size);
					$('#cup-progress-files').text(
						json.data.remaining_files
					);
					$('#cup-upload-progress').show();
					$('#cup-sync-progress-bar')
						.css('width', json.data.pcnt_complete + '%')
						.attr('aria-valuenow', json.data.pcnt_complete)
						.text(json.data.pcnt_complete + '%');
				} else {
					showError(json.data);
				}
			},
			'json'
		)
			.fail(function () {
				showError(cloud_uploads_data.strings.ajax_error);
			})
			.always(function () {
				setTimeout(function () {
					getSyncStatus();
				}, 15000);
			});
	};

	var deleteFiles = function () {
		if (cupStopLoop) {
			cupStopLoop = false;
			return false;
		}

		$.post(
			ajaxurl + '?action=cloud-uploads-delete',
			{nonce: cloud_uploads_data.nonce.delete},
			function (json) {
				if (json.success) {
					//$('.cup-progress-pcnt').text(json.data.pcnt_complete);
					$('#cup-delete-size').text(json.data.deletable_size);
					$('#cup-delete-files').text(json.data.deletable_files);
					if (!json.data.is_done) {
						deleteFiles();
					} else {
						location.reload();
						return true;
					}
				} else {
					showError(json.data);
					$('.modal').modal('hide');
				}
			},
			'json'
		).fail(function () {
			showError(cloud_uploads_data.strings.ajax_error);
			$('.modal').modal('hide');
		});
	};

	var downloadFiles = function (nonce = '') {
		if (cupStopLoop) {
			cupStopLoop = false;
			cupProcessingLoop = false;
			return false;
		}
		cupProcessingLoop = true;

		var data = {};
		if (nonce) {
			data.nonce = nonce;
		} else {
			data.nonce = cloud_uploads_data.nonce.download;
		}
		$.post(
			ajaxurl + '?action=cloud-uploads-download',
			data,
			function (json) {
				cupLoopErrors = 0;
				if (json.success) {
					//$('.cup-progress-pcnt').text(json.data.pcnt_complete);
					$('#cup-download-size').text(json.data.deleted_size);
					$('#cup-download-files').text(json.data.deleted_files);
					$('#cup-download-progress').show();
					$('#cup-download-progress-bar')
						.css('width', json.data.pcnt_downloaded + '%')
						.attr('aria-valuenow', json.data.pcnt_downloaded)
						.text(json.data.pcnt_downloaded + '%');
					if (!json.data.is_done) {
						data.nonce = json.data.nonce; //save for future errors
						downloadFiles(json.data.nonce);
					} else {
						cupProcessingLoop = false;
						location.reload();
						return true;
					}
					if (
						Array.isArray(json.data.errors) &&
						json.data.errors.length
					) {
						$.each(json.data.errors, function (i, value) {
							$('#cup-download-errors ul').append(
								'<li><span class="dashicons dashicons-warning"></span> ' +
								value +
								'</li>'
							);
						});
						$('#cup-download-errors').show();
						var scroll = $('#cup-download-errors')[0]
							.scrollHeight;
						$('#cup-download-errors').animate(
							{scrollTop: scroll},
							5000
						);
					}
				} else {
					showError(json.data);
					$('.modal').modal('hide');
				}
			},
			'json'
		).fail(function () {
			//if we get an error like 504 try up to 6 times before giving up.
			cupLoopErrors++;
			if (cupLoopErrors > 6) {
				showError(cloud_uploads_data.strings.ajax_error);
				$('.modal').modal('hide');
				cupLoopErrors = 0;
				cupProcessingLoop = false;
			} else {
				var exponentialBackoff = Math.floor(
					Math.pow(cupLoopErrors, 2.5) * 1000
				); //max 90s
				console.log(
					'Server error. Waiting ' +
					exponentialBackoff +
					'ms before retrying'
				);
				setTimeout(function () {
					downloadFiles(data.nonce);
				}, exponentialBackoff);
			}
		});
	};

	//Scan
	$('#scan-modal')
		.on('show.bs.modal', function () {
			$('#cup-error').hide();
			cupStopLoop = false;
			buildFilelist([]);
		})
		.on('hide.bs.modal', function () {
			cupStopLoop = true;
			cupProcessingLoop = false;
		});

	//Compare to live
	$('#scan-remote-modal')
		.on('show.bs.modal', function (e) {
			$('#cup-error').hide();
			cupStopLoop = false;
			var button = $(e.relatedTarget); // Button that triggered the modal
			window.cupNextStep = button.data('next'); // Extract info from data-* attributes
			fetchRemoteFilelist(null);
		})
		.on('hide.bs.modal', function () {
			cupStopLoop = true;
			cupProcessingLoop = false;
		});

	//Sync
	$('#upload-modal')
		.on('show.bs.modal', function () {
			$('.cup-enable-errors').hide(); //hide errors on enable modal
			$('#cup-collapse-errors').collapse('hide');
			$('#cup-error').hide();
			$('#cup-sync-errors').hide();
			$('#cup-sync-errors ul').empty();
			cupStopLoop = false;
			syncFilelist();
			setTimeout(function () {
				getSyncStatus();
			}, 15000);
		})
		.on('shown.bs.modal', function () {
			$('#scan-remote-modal').modal('hide');
		})
		.on('hide.bs.modal', function () {
			cupStopLoop = true;
			cupProcessingLoop = false;
			cupAjaxCall.abort();
		});

	//Make sure upload modal closes
	$('#enable-modal')
		.on('shown.bs.modal', function () {
			$('#upload-modal').modal('hide');
		})
		.on('hidden.bs.modal', function () {
			$('#cup-enable-spinner').addClass('text-hide');
			$('#cup-enable-button').show();
		});

	$('#cup-collapse-errors').on('show.bs.collapse', function () {
		// load up list of errors via ajax
		$.get(
			ajaxurl + '?action=cloud-uploads-sync-errors',
			function (json) {
				if (json.success) {
					$('#cup-collapse-errors .list-group').html(json.data);
				}
			},
			'json'
		);
	});

	$('#cup-resync-button').on('click', function (e) {
		$('.cup-enable-errors').hide(); //hide errors on enable modal
		$('#cup-collapse-errors').collapse('hide');
		$('#cup-enable-button').hide();
		$('#cup-enable-spinner').removeClass('text-hide');
		$.post(
			ajaxurl + '?action=cloud-uploads-reset-errors',
			{foo: 'bar'},
			function (json) {
				if (json.success) {
					$('.modal').modal('hide');
					$('#upload-modal').modal('show');
					return true;
				}
			},
			'json'
		).fail(function () {
			showError(cloud_uploads_data.strings.ajax_error);
			$('.modal').modal('hide');
		});
	});

	//Download
	$('#download-modal')
		.on('show.bs.modal', function () {
			$('#cup-error').hide();
			$('#cup-download-errors').hide();
			$('#cup-download-errors ul').empty();
			cupStopLoop = false;
			downloadFiles();
		})
		.on('hide.bs.modal', function () {
			cupStopLoop = true;
			cupProcessingLoop = false;
		});

	//Delete
	$('#delete-modal')
		.on('show.bs.modal', function () {
			$('#cup-error').hide();
			cupStopLoop = false;
			$('#cup-delete-local-button').show();
			$('#cup-delete-local-spinner').hide();
		})
		.on('hide.bs.modal', function () {
			cupStopLoop = true;
		});

	//Delete local files
	$('#cup-delete-local-button').on('click', function () {
		$(this).hide();
		$('#cup-delete-local-spinner').show();
		deleteFiles();
	});

	//Enable Cloud uploads
	$('#cup-enable-button').on('click', function () {
		$('.cup-enable-errors').hide(); //hide errors on enable modal
		$('#cup-collapse-errors').collapse('hide');
		$('#cup-enable-button').hide();
		$('#cup-enable-spinner').removeClass('text-hide');
		$.post(
			ajaxurl + '?action=cloud-uploads-toggle',
			{enabled: true, nonce: cloud_uploads_data.nonce.toggle},
			function (json) {
				if (json.success) {
					location.reload();
					return true;
				}
			},
			'json'
		).fail(function () {
			showError(cloud_uploads_data.strings.ajax_error);
			$('#cup-enable-spinner').addClass('text-hide');
			$('#cup-enable-button').show();
			$('.modal').modal('hide');
		});
	});

	//Enable video cloud
	$('#cup-enable-video-button').on('click', function () {
		$('#cup-enable-video-button').hide();
		$('#cup-enable-video-spinner').removeClass('d-none').addClass('d-block');
		$.post(
			ajaxurl + '?action=cloud-uploads-video-activate',
			{nonce: cloud_uploads_data.nonce.video},
			function (json) {
				if (json.success) {
					location.reload();
					return true;
				} else {
					$('#cup-enable-video-spinner').addClass('d-none').removeClass('d-block');
					$('#cup-enable-video-button').show();
				}
			},
			'json'
		).fail(function () {
			showError(cloud_uploads_data.strings.ajax_error);
			$('#cup-enable-video-spinner').addClass('d-none').removeClass('d-block');
			$('#cup-enable-video-button').show();
		});
	});

	//refresh api data
	$('.cup-refresh-icon .dashicons').on('click', function () {
		$(this).hide();
		$('.cup-refresh-icon .spinner-grow').removeClass('text-hide');
		window.location = $(this).attr('data-target');
	});

	//Charts
	var bandwidthFormat = function (bytes) {
		if (bytes < 1024) {
			return bytes + ' B';
		} else if (bytes < 1024 * 1024) {
			return Math.round(bytes / 1024) + ' KB';
		} else if (bytes < 1024 * 1024 * 1024) {
			return Math.round((bytes / 1024 / 1024) * 10) / 10 + ' MB';
		} else {
			return (
				Math.round((bytes / 1024 / 1024 / 1024) * 100) / 100 + ' GB'
			);
		}
	};

	var sizelabel = function (tooltipItem, data) {
		var label = ' ' + data.labels[tooltipItem.index] || '';
		return label;
	};

	window.onload = function () {
		var pie1 = document.getElementById('cup-local-pie');
		if (pie1) {
			var config_local = {
				type: 'pie',
				data: cloud_uploads_data.local_types,
				options: {
					responsive: true,
					legend: false,
					tooltips: {
						callbacks: {
							label: sizelabel,
						},
						backgroundColor: '#F1F1F1',
						bodyFontColor: '#2A2A2A',
					},
					title: {
						display: true,
						position: 'bottom',
						fontSize: 18,
						fontStyle: 'normal',
						text: cloud_uploads_data.local_types.total,
					},
				},
			};

			var ctx = pie1.getContext('2d');
			window.myPieLocal = new Chart(ctx, config_local);
		}

		var pie2 = document.getElementById('cup-cloud-pie');
		if (pie2) {
			var config_cloud = {
				type: 'pie',
				data: cloud_uploads_data.cloud_types,
				options: {
					responsive: true,
					legend: false,
					tooltips: {
						callbacks: {
							label: sizelabel,
						},
						backgroundColor: '#F1F1F1',
						bodyFontColor: '#2A2A2A',
					},
					title: {
						display: true,
						position: 'bottom',
						fontSize: 18,
						fontStyle: 'normal',
						text: cloud_uploads_data.cloud_types.total,
					},
				},
			};

			var ctx = pie2.getContext('2d');
			window.myPieCloud = new Chart(ctx, config_cloud);
		}
	};
});
