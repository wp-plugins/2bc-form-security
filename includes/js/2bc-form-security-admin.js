jQuery(document).ready(function($) {
	
	var api_lock = [false,false];
	var $enable_recaptcha = $('input[id*="twobc_formsecurity_options[enable_recaptcha]"]');
	var $site_key = $('input[id*="twobc_formsecurity_options[site_key]"]');
	var $secret_key = $('input[id*="twobc_formsecurity_options[secret_key]"]');
	var $site_and_secret = $('input[id*="twobc_formsecurity_options[site_key]"],input[id*="twobc_formsecurity_options[secret_key]"]');
	var $recaptchaTheme = $('select[id="twobc_formsecurity_options[recaptcha_theme]"]');
	var site_key = $site_key.val();
	var secret_key = $secret_key.val();
	
	var twobc_verify_callback = function(googleResponse) {
		var googleAjaxArgs = {
			'action': 'twobcfs_recacptcha_verify_api',
			'_ajax_nonce': twoBCFormSecurity._ajax_nonce,
			'twobcfsRecaptchaResponse': googleResponse,
			'twobcfs_site_key': site_key,
			'twobcfs_secret_key': secret_key
		};
		
		$.post(ajaxurl, googleAjaxArgs, function (response) {
			var newdiv = document.getElementById('twobc_api_render_check');
			var responsemessage = document.createElement('p');
			if (response) {
				response = JSON.parse(response);				
				if ( response.success ) {
					$enable_recaptcha.prop('checked', true);
					validation_population(1);
					if(!$enable_recaptcha.hasClass('recaptcha_valid')){$enable_recaptcha.addClass('recaptcha_valid');}
					$site_and_secret.prop('disabled', true);
					if(newdiv) {
						responsemessage.innerHTML = twoBCFormSecurity.instructMessage2;
						responsemessage.className = 'twobc_recapt_message_success';
						$(newdiv).append(responsemessage);
						$('.twobc_recapt_message_instruct').remove();
					}
				} else {
					if ( response['error-codes'] ) {
						if ( -1 != response['error-codes'].indexOf('invalid-input-secret') ) {
							responsemessage.innerHTML = twoBCFormSecurity.errorSecretKey;
							responsemessage.className = 'return_error';							
						}
						if (-1 != response['error-codes'].indexOf('invalid-input-response')) {
							responsemessage.innerHTML = twoBCFormSecurity.errorResponse;
							responsemessage.className = 'return_error';
						}
					} else {
						responsemessage.innerHTML = twoBCFormSecurity.errorGeneric;
						responsemessage.className = 'return_error';						
					}
					if(newdiv) {
						$(newdiv).append(responsemessage);
					}
					$('.twobc_recapt_message_instruct').remove();
					$enable_recaptcha.prop('checked', false);
					$site_and_secret.prop('disabled', false);
					$enable_recaptcha.removeClass('recaptcha_valid');
					validation_population(0);
				}
			} else {
				responsemessage.innerHTML = twoBCFormSecurity.noresponse;
				responsemessage.className = 'return_error';
				$('.twobc_recapt_message_instruct').remove();
				if(newdiv) {
					$(newdiv).append(responsemessage);
				}
			}
		});
	};
	
	$('#twobcfs_change_api').click(function(){
		$enable_recaptcha.prop('checked', false);
		$enable_recaptcha.prop('disabled', true);
		$enable_recaptcha.removeClass('recaptcha_valid');
		$site_and_secret.prop('disabled', false);
		api_lock = [false,false];
		var newdiv = document.getElementById('twobc_api_render_check');
		if(newdiv) {
			newdiv.innerHTML = '';
		}
		validation_population(0);
		state_eval();
	});
	
	$enable_recaptcha.change(function(){
		if(this.checked){
			if(!$(this).hasClass('recaptcha_valid')) {
				this.checked = false;
				site_key = $site_key.val();
				secret_key = $secret_key.val();
				var newdiv = document.getElementById('twobc_api_render_check');
				if(newdiv) {
					newdiv.innerHTML = '';
				} else {
					var newdiv = document.createElement('div');
					newdiv.id = 'twobc_api_render_check';
					this.parentNode.appendChild(newdiv);
				}
				if(!$('.twobc_recapt_message_instruct').length) {
					var instructmessage = document.createElement('p');
					instructmessage.innerHTML = twoBCFormSecurity.instructMessage1;
					instructmessage.className = 'twobc_recapt_message_instruct';
					newdiv.parentNode.insertBefore(instructmessage,newdiv);
				}
				var gcaptreturn = grecaptcha.render('twobc_api_render_check', {
					'sitekey' : site_key,
					'callback' : twobc_verify_callback,
					'theme' : $recaptchaTheme.val()
				});
			}
		} else {
			var newdiv = document.getElementById('twobc_api_render_check');
			if(newdiv) {
				newdiv.innerHTML = '';
			}
		}
	});
	
	function validation_population(val) {
		var hiddensitekey = document.getElementById('twobc_formsecurity_hidden_sitekey');
		var hiddensecret = document.getElementById('twobc_formsecurity_hidden_secret');
		if($site_key.length && $secret_key.length) {
			if(val==1) {
				if(hiddensitekey && hiddensecret) {
					hiddensitekey.value =  $site_key.val();
					hiddensecret.value =  $secret_key.val();
				} else {
					hiddensitekey = document.createElement('input');
					hiddensitekey.type = 'hidden';
					hiddensitekey.name = $site_key.attr('name');
					hiddensitekey.id = 'twobc_formsecurity_hidden_sitekey';
					hiddensitekey.value =  $site_key.val();
					hiddensecret = document.createElement('input');
					hiddensecret.type = 'hidden';
					hiddensecret.name = $secret_key.attr('name');
					hiddensecret.id = 'twobc_formsecurity_hidden_secret';
					hiddensecret.value =  $secret_key.val();
					$secret_key.parent().append(hiddensitekey);
					$secret_key.parent().append(hiddensecret);
				}
			} else {
				if(hiddensitekey && hiddensecret) {
					$('#twobc_formsecurity_hidden_sitekey,#twobc_formsecurity_hidden_secret').remove();
				}
			}
			var validmeta = document.getElementById('twobc_formsecurity_nonce_recaptcha_valid');
			var validmeta1 = document.getElementById('twobc_formsecurity_options[recaptcha_valid]');
			if(validmeta && validmeta1) {
				validmeta1.value = val;
			} else {
				validmeta1 = document.createElement('input');
				validmeta1.type = 'hidden';
				validmeta1.name = 'twobc_formsecurity_options[recaptcha_valid]';
				validmeta1.id = 'twobc_formsecurity_options[recaptcha_valid]';
				validmeta1.value = val;
				validmeta = document.createElement('input');
				validmeta.type = 'hidden';
				validmeta.name = 'twobc_formsecurity_nonce_recaptcha_valid';
				validmeta.id = 'twobc_formsecurity_nonce_recaptcha_valid';
				validmeta.value = twoBCFormSecurity.recaptchaValidNonce;
				$enable_recaptcha.parent().append(validmeta);
				$enable_recaptcha.parent().append(validmeta1);
			}
		}
	}
	
	function state_eval() {
		if($site_key.length && $secret_key.length) {
			if($site_key.val().length >= 35) {api_lock[0] = true;} else {api_lock[0] = false;}
			if($secret_key.val().length >= 35) {api_lock[1] = true;} else {api_lock[1] = false;}
			if(api_lock[0] && api_lock[1]) {
				$enable_recaptcha.prop('disabled', false);
			} else {
				$enable_recaptcha.prop('checked', false);
				$enable_recaptcha.prop('disabled', true);
				$enable_recaptcha.removeClass('recaptcha_valid');
				$site_and_secret.prop('disabled', false);
			}
			var newdiv = document.getElementById('twobc_api_render_check');
			if(newdiv) {
				newdiv.innerHTML = '';
			}
			if($('.twobc_recapt_message_instruct').length) {
				$('.twobc_recapt_message_instruct').remove();
			}
		}
	}	
	$site_key.change(function(){state_eval();validation_population(0);});
	$secret_key.change(function(){state_eval();validation_population(0);});
	state_eval();
	
	//Clear Report Button
	$("#twobc_formsecurity_options_wrap").find(".twobc_formsecurity_clear_log_btn").click(function(e){
		var confirmed = confirm(twoBCFormSecurity.clearLogsButton);
		if(confirmed == true) {
			var ajaxData = {
				'action' : 'twobc_formsecurity_reset_log',
				'_ajax_nonce' : twoBCFormSecurity._ajax_nonce
			};
			$.post(ajaxurl, ajaxData, function(response){
				var qs = window.location.href.split('?');
				var refreshurl = window.location.href;
				if(qs[1]) {
					var qsargs = qs[1].split('&');
					for(var a=0;a<qsargs.length;a++) {
						if(qsargs[a].indexOf('page_num') != -1) {
							qs[1] = qs[1].replace('&'+qsargs[a],'');
						}
					}
					refreshurl = qs[0]+'?'+qs[1];
				}
				window.location = refreshurl;
			});
		} else {
			
		}
	});
});