<?php echo $header; ?>
<?php echo $column_left; ?>
<div id="content">
	<style>
		#mt_jet_credit_tabs > li > a:focus,
		#mt_jet_credit_tabs > li > a:active {
			outline: none;
			box-shadow: none;
		}

		.mt-jet-switch {
			position: relative;
			display: inline-block;
			width: 50px;
			height: 26px;
			margin: 0;
			vertical-align: middle;
		}
		.mt-jet-switch input {
			opacity: 0;
			width: 0;
			height: 0;
			position: absolute;
		}
		.mt-jet-slider {
			position: absolute;
			cursor: pointer;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background-color: #ccc;
			transition: 0.25s;
			border-radius: 26px;
		}
		.mt-jet-slider:before {
			position: absolute;
			content: "";
			height: 20px;
			width: 20px;
			left: 3px;
			bottom: 3px;
			background-color: #fff;
			transition: 0.25s;
			border-radius: 50%;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
		}
		.mt-jet-switch input:checked + .mt-jet-slider {
			background-color: #337ab7;
		}
		.mt-jet-switch input:checked + .mt-jet-slider:before {
			transform: translateX(24px);
		}
		.mt-jet-switch input:focus + .mt-jet-slider {
			box-shadow: 0 0 2px rgba(51, 122, 183, 0.5);
		}
		.mt-jet-switch-label {
			display: inline-block;
			margin-left: 10px;
			vertical-align: middle;
			color: #555;
		}

		.mt-jet-button-preview {
			width: 100%;
			max-width: 100%;
			box-sizing: border-box;
			margin-top: 12px;
			padding: 10px 12px;
			background: #f9f9f9;
			border: 1px solid #e3e3e3;
			border-radius: 4px;
		}
		.mt-jet-preview-standard-inner {
			display: flex;
			flex-wrap: wrap;
			gap: 16px;
			align-items: flex-start;
		}
		.mt-jet-preview-table {
			text-align: center;
			border: 0;
		}
		.mt-jet-preview-table .mt-jet-preview-cell {
			padding: 2px 0;
			border: 0;
		}
		.mt-jet-preview-table img {
			margin: 0;
			padding: 0;
			max-width: 155px;
			border: 0;
			background: transparent;
			vertical-align: middle;
		}
		.mt-jet-wide-button-wrap {
			width: 100%;
			max-width: 100%;
			box-sizing: border-box;
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			gap: 10px;
			--jet-wide-radius: 16px;
			--jet-wide-max-width: 570px;
			--jet-wide-font-size: 14px;
			--jet-wide-bg: #ffffff;
			--jet-wide-hover: #f0fdf4;
			--jet-wide-border: #166534;
			--jet-wide-color: #166534;
			--jet-wide-color-second: #000000;
		}
		.mt-jet-wide-button {
			width: 100%;
			max-width: var(--jet-wide-max-width, 570px);
			height: 50px;
			max-height: 50px;
			border: 2px solid var(--jet-wide-border, #166534);
			background: var(--jet-wide-bg, #fff);
			border-radius: var(--jet-wide-radius, 16px);
			padding: 6px 12px;
			display: flex;
			flex-direction: column;
			justify-content: center;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
			box-sizing: border-box;
		}
		.mt-jet-wide-button-head {
			font-size: var(--jet-wide-font-size, 14px);
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 6px;
			font-weight: 700;
			color: var(--jet-wide-color, #166534);
			line-height: 1.2;
			white-space: nowrap;
		}
		.mt-jet-wide-button-logo {
			height: 16px;
			width: auto;
		}
		.mt-jet-wide-button-text {
			font-size: var(--jet-wide-font-size, 14px);
			color: var(--jet-wide-color, #166534);
			line-height: 1.2;
			text-align: center;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.mt-jet-wide-button-text-second {
			font-size: 75%;
			font-weight: 400;
			color: var(--jet-wide-color-second, #000000);
		}
		.jet_scheme_grid_wrap {
			width: 100%;
			overflow-x: auto;
			-webkit-overflow-scrolling: touch;
			padding: 4px;
			box-sizing: border-box;
		}
		.jet_scheme_grid {
			display: grid;
			grid-template-columns: repeat(7, minmax(72px, 1fr));
			gap: 10px;
			min-width: min-content;
		}
		.jet_scheme_card {
			display: block;
			margin: 0;
			cursor: pointer;
			box-sizing: border-box;
		}
		.jet_scheme_radio {
			position: absolute;
			clip: rect(0, 0, 0, 0);
			width: 1px;
			height: 1px;
			margin: -1px;
			padding: 0;
			overflow: hidden;
			border: 0;
		}
		.jet_scheme_preview {
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 48px;
			padding: 6px 4px;
			box-sizing: border-box;
			width: 100%;
			transition: box-shadow 0.15s ease, transform 0.15s ease;
		}
		.jet_scheme_preview_text {
			font-size: 11px;
			font-weight: 700;
			line-height: 1.15;
			text-align: center;
			word-break: break-word;
		}
		.jet_scheme_card:hover .jet_scheme_preview {
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
		}
		.jet_scheme_card.is-selected .jet_scheme_preview {
			outline: 3px solid #dc2626;
			outline-offset: 1px;
			box-shadow: none;
		}
		p.jet_scheme_summary {
			margin: 12px 0 0;
			font-size: 13px;
			font-weight: 600;
			color: #1d2327;
		}
	</style>

	<?php if ($error_warning) { ?>
	<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
		<button type="button" class="close" data-dismiss="alert">&times;</button>
	</div>
	<?php } ?>

	<?php if ($success) { ?>
		<div class="alert alert-success alert-dismissible">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<?php echo $success; ?>
		</div>
	<?php } ?>

	<div class="page-header">
		<div class="container-fluid">
			<div class="pull-right">
				<button type="submit" form="form-html" data-toggle="tooltip" title="Запиши" class="btn btn-primary"><i class="fa fa-save"></i></button>
				<a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="Откажи" class="btn btn-default"><i class="fa fa-reply"></i></a>
			</div>
			<h1><?php echo $heading_title; ?></h1>
			<ul class="breadcrumb">
			<?php foreach ($breadcrumbs as $breadcrumb) { ?>
				<li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
			<?php } ?>
			</ul>
		</div>
	</div>

	<div class="container-fluid">
		<form action="<?php echo $form_action; ?>" method="post" enctype="multipart/form-data" id="form-html" class="form-horizontal">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title"><i class="fa fa-pencil"></i> Настройки на модула</h3>
				</div>
				<div class="panel-body">
					<ul class="nav nav-tabs" id="mt_jet_credit_tabs">
						<li class="active"><a href="#tab-management" data-toggle="tab">Управление</a></li>
						<li><a href="#tab-functional" data-toggle="tab">Функционални настройки</a></li>
						<li><a href="#tab-visual" data-toggle="tab">Визуални настройки</a></li>
						<li><a href="#tab-filters" data-toggle="tab">Форма за въвеждане на лихвени филтри</a></li>
					</ul>

					<div class="tab-content">

						<div class="tab-pane active" id="tab-management">
							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_status">Включи модула</label>
								<div class="col-sm-8">
									<input type="hidden" name="mt_jet_credit_status" value="0" />
									<label class="mt-jet-switch">
										<input type="checkbox" name="mt_jet_credit_status" value="1" id="mt_jet_credit_status" <?php if ($mt_jet_credit_status == 1) { echo 'checked'; } ?> />
										<span class="mt-jet-slider"></span>
									</label>
									<span class="mt-jet-switch-label"><?php echo $mt_jet_credit_status == 1 ? 'Включен' : 'Изключен'; ?></span>
									<div class="text-small">Показвай бутоните за закупуване на кредит през ПБ Лични Финанси</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_cart_show">Покажи бутона в количката</label>
								<div class="col-sm-8">
									<input type="hidden" name="mt_jet_credit_cart_show" value="0" />
									<label class="mt-jet-switch">
										<input type="checkbox" name="mt_jet_credit_cart_show" value="1" id="mt_jet_credit_cart_show" <?php if ($mt_jet_credit_cart_show == 1) { echo 'checked'; } ?> />
										<span class="mt-jet-slider"></span>
									</label>
									<span class="mt-jet-switch-label"><?php echo $mt_jet_credit_cart_show == 1 ? 'Включен' : 'Изключен'; ?></span>
									<div class="text-small">Показвай бутона за закупуване на кредит през ПБ Лични Финанси в количката</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_status_card">Покажи бутона за изпращане чрез кредитна карта</label>
								<div class="col-sm-8">
									<input type="hidden" name="mt_jet_credit_status_card" value="0" />
									<label class="mt-jet-switch">
										<input type="checkbox" name="mt_jet_credit_status_card" value="1" id="mt_jet_credit_status_card" <?php if ($mt_jet_credit_status_card == 1) { echo 'checked'; } ?> />
										<span class="mt-jet-slider"></span>
									</label>
									<span class="mt-jet-switch-label"><?php echo $mt_jet_credit_status_card == 1 ? 'Включен' : 'Изключен'; ?></span>
									<div class="text-small">Показвай бутона за закупуване на кредит през ПБ Лични Финанси със заявката за лизинг направена по метода с кредитна карта</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_vnoska">Покажи вноска</label>
								<div class="col-sm-8">
									<input type="hidden" name="mt_jet_credit_vnoska" value="0" />
									<label class="mt-jet-switch">
										<input type="checkbox" name="mt_jet_credit_vnoska" value="1" id="mt_jet_credit_vnoska" <?php if ($mt_jet_credit_vnoska == 1) { echo 'checked'; } ?> />
										<span class="mt-jet-slider"></span>
									</label>
									<span class="mt-jet-switch-label"><?php echo $mt_jet_credit_vnoska == 1 ? 'Включен' : 'Изключен'; ?></span>
									<div class="text-small">Дали да се показва текст, в дясно от бутона указващ месечната вноска за избрания период на лизинг?</div>
								</div>
							</div>
					</div>

						<div class="tab-pane" id="tab-functional">
							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_mail">Избери email за изпращане</label>
								<div class="col-sm-8">
									<input type="text" name="mt_jet_credit_mail" value="<?php echo $mt_jet_credit_mail; ?>" placeholder="Избери email за изпращане" id="mt_jet_credit_mail" class="form-control" />
									<div class="small">Избери email адрес на който ще се изпращат заявките от клиента. Използвайте "pf-online.shop@postbank.bg" за изпращане на заявките към ПБ Лични Финанси. Можете да въведете повече от един мейл адрес, като ги разделите помежду си със запетая. (Ако оставите празно ще се използва email-а по-подразбиране за системата. Препоръчва се използването на email извън Вашия домейн за да се избегне ауто спан защитата.)</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_id">Избери идентификатор на магазина за изпращане</label>
								<div class="col-sm-8">
									<input type="text" name="mt_jet_credit_id" value="<?php echo $mt_jet_credit_id; ?>" placeholder="Избери идентификатор на магазина за изпращане" id="mt_jet_credit_id" class="form-control" />
									<div class="small">Избери идентификатор на магзина, който да се изпраща към Банката заедно със заявката за лизинг. По този идентификатор Банката ще разпознава Вашите заявки</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_vnoski">Избор на брой вноски по-подразбиране</label>
								<div class="col-sm-8">
									<select name="mt_jet_credit_vnoski" id="mt_jet_credit_vnoski" class="form-control">
										<option value=3 <?php if ($mt_jet_credit_vnoski == 3) { echo 'selected'; } ?>>3 месеца</option>
										<option value=6 <?php if ($mt_jet_credit_vnoski == 6) { echo 'selected'; } ?>>6 месеца</option>
										<option value=9 <?php if ($mt_jet_credit_vnoski == 9) { echo 'selected'; } ?>>9 месеца</option>
										<option value=12 <?php if ($mt_jet_credit_vnoski == 12) { echo 'selected'; } ?>>12 месеца</option>
										<option value=15 <?php if ($mt_jet_credit_vnoski == 15) { echo 'selected'; } ?>>15 месеца</option>
										<option value=18 <?php if ($mt_jet_credit_vnoski == 18) { echo 'selected'; } ?>>18 месеца</option>
										<option value=24 <?php if ($mt_jet_credit_vnoski == 24) { echo 'selected'; } ?>>24 месеца</option>
										<option value=30 <?php if ($mt_jet_credit_vnoski == 30) { echo 'selected'; } ?>>30 месеца</option>
										<option value=36 <?php if ($mt_jet_credit_vnoski == 36) { echo 'selected'; } ?>>36 месеца</option>
									</select>
									<div class="text-small">Избор на брой вноски по-подразбиране в продуктовата страница</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_purcent">Избор на процент лихва</label>
								<div class="col-sm-8">
									<select name="mt_jet_credit_purcent" id="mt_jet_credit_purcent" class="form-control">
										<option value=0.00 <?php if ($mt_jet_credit_purcent == 0.00) { echo 'selected'; } ?>>0.00%</option>
										<option value=0.80 <?php if ($mt_jet_credit_purcent == 0.80) { echo 'selected'; } ?>>0.80%</option>
										<option value=0.99 <?php if ($mt_jet_credit_purcent == 0.99) { echo 'selected'; } ?>>0.99%</option>
										<option value=1.00 <?php if ($mt_jet_credit_purcent == 1.00) { echo 'selected'; } ?>>1.00%</option>
										<option value=1.10 <?php if ($mt_jet_credit_purcent == 1.10) { echo 'selected'; } ?>>1.10%</option>
										<option value=1.20 <?php if ($mt_jet_credit_purcent == 1.20) { echo 'selected'; } ?>>1.20%</option>
										<option value=1.40 <?php if ($mt_jet_credit_purcent == 1.40) { echo 'selected'; } ?>>1.40%</option>
									</select>
									<div class="text-small">Избор на процент лихва</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_purcent_card">Процент за лихва чрез кредитна карта</label>
								<div class="col-sm-8">
									<select name="mt_jet_credit_purcent_card" id="mt_jet_credit_purcent_card" class="form-control">
										<option value=0.00 <?php if ($mt_jet_credit_purcent_card == 0.00) { echo 'selected'; } ?>>0.00%</option>
										<option value=0.80 <?php if ($mt_jet_credit_purcent_card == 0.80) { echo 'selected'; } ?>>0.80%</option>
										<option value=0.90 <?php if ($mt_jet_credit_purcent_card == 0.90) { echo 'selected'; } ?>>0.90%</option>
										<option value=0.99 <?php if ($mt_jet_credit_purcent_card == 0.99) { echo 'selected'; } ?>>0.99%</option>
										<option value=1.00 <?php if ($mt_jet_credit_purcent_card == 1.00) { echo 'selected'; } ?>>1.00%</option>
										<option value=1.10 <?php if ($mt_jet_credit_purcent_card == 1.10) { echo 'selected'; } ?>>1.10%</option>
										<option value=1.20 <?php if ($mt_jet_credit_purcent_card == 1.20) { echo 'selected'; } ?>>1.20%</option>
										<option value=1.40 <?php if ($mt_jet_credit_purcent_card == 1.40) { echo 'selected'; } ?>>1.40%</option>
									</select>
									<div class="text-small">Избор на таблица за процент за лихва за заявката за лизинг направена по метода с кредитна карта</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_count">№ Поръчка</label>
								<div class="col-sm-8">
									<input type="text" readonly name="mt_jet_credit_count" value="<?php echo $mt_jet_credit_count; ?>" placeholder="Номер на текущата поръчка за лизинг." id="mt_jet_credit_count" class="form-control" />
									<div class="small">Номер на текущата поръчка за лизинг</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_price">Минимална сума</label>
								<div class="col-sm-8">
									<input type="number" min="0" max="999999" step="1" name="mt_jet_credit_price" value="<?php echo $mt_jet_credit_price; ?>" placeholder="Минимална сума (евро)" id="mt_jet_credit_price" class="form-control" />
									<div class="small">Минимално възможната сума на стоките за закупуване на кредит през ПБ Лични Финанси</div>
								</div>
							</div>
						</div>

						<div class="tab-pane" id="tab-visual">
							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_gap">Празно място над бутона</label>
								<div class="col-sm-8">
									<input type="number" min="0" max="999999" step="1" name="mt_jet_credit_gap" value="<?php echo $mt_jet_credit_gap; ?>" id="mt_jet_credit_gap" class="form-control" />
									<div class="small">Празно място над бутона в px. Използва се за подредба на бутоните, когато са повече от един. Можете да въведете 0 ако не желаете да има празно място</div>
								</div>
							</div>

							<div class="form-group">
								<label class="col-sm-4 control-label" for="mt_jet_credit_button_type">Вид на бутона</label>
								<div class="col-sm-8">
									<select name="mt_jet_credit_button_type" id="mt_jet_credit_button_type" class="form-control">
										<option value="standard" <?php if ($mt_jet_credit_button_type == 'standard') { echo 'selected'; } ?>>Стандартен бутон</option>
										<option value="wide" <?php if ($mt_jet_credit_button_type == 'wide') { echo 'selected'; } ?>>Персонализиран бутон</option>
									</select>
									<div class="small">От тук можете да си изберете вида на бутоните които ще се показват в продуктовата и в страницата количка.</div>
									<div class="mt-jet-button-preview">
										<div id="mt-jet-preview-standard" style="display: <?php echo $mt_jet_credit_button_type == 'wide' ? 'none' : 'block'; ?>;">
											<div class="mt-jet-preview-standard-inner">
												<table class="mt-jet-preview-table" cellspacing="0" cellpadding="0">
													<tbody>
														<tr>
															<td class="mt-jet-preview-cell">
																<img src="<?php echo $mt_jet_preview_jet; ?>" alt="Кредитен модул ПБ Лични Финанси" title="Кредитен модул ПБ Лични Финанси" />
															</td>
														</tr>
													</tbody>
												</table>
											</div>
										</div>
										<div id="mt-jet-preview-wide" style="display: <?php echo $mt_jet_credit_button_type == 'wide' ? 'block' : 'none'; ?>;">
											<div class="mt-jet-wide-dual-preview" style="display:flex;flex-direction:column;align-items:flex-start;gap:10px;width:100%;">
												<div id="mt-jet-wide-preview-wrap" class="mt-jet-wide-button-wrap" style="<?php echo $mt_jet_wide_button_wrap_style; ?>">
													<div class="mt-jet-wide-button" role="img" aria-label="Преглед — основен бутон">
														<div class="mt-jet-wide-button-head">
															<span id="mt-jet-btn-text-preview"><?php echo $mt_jet_credit_btn_text; ?></span>
															<img id="mt-jet-wide-preview-logo-main" src="<?php echo $mt_jet_preview_jet_logo; ?>" alt="ПБ Лични Финанси" class="mt-jet-wide-button-logo"<?php if ($mt_jet_credit_btn_logo != 1) { echo ' style="display:none"'; } ?> />
														</div>
														<div class="mt-jet-wide-button-text">12 x 97.33 евро
														</div>
													</div>
												</div>
												<div id="mt-jet-wide-preview-wrap-card" class="mt-jet-wide-button-wrap" style="<?php echo $mt_jet_wide_button_wrap_style; ?>">
													<div class="mt-jet-wide-button" role="img" aria-label="Преглед — картов бутон">
														<div class="mt-jet-wide-button-head">
															<span id="mt-jet-btn-text-card-preview"><?php echo $mt_jet_credit_btn_text_card; ?></span>
															<img id="mt-jet-wide-preview-logo-card" src="<?php echo $mt_jet_preview_jet_logo; ?>" alt="ПБ Лични Финанси" class="mt-jet-wide-button-logo"<?php if ($mt_jet_credit_btn_logo != 1) { echo ' style="display:none"'; } ?> />
														</div>
														<div class="mt-jet-wide-button-text">12 x 97.33 евро
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<div id="mt-jet-wide-only-settings" style="display: <?php echo $mt_jet_credit_button_type == 'wide' ? 'block' : 'none'; ?>;">
								<div class="form-group">
									<label class="col-sm-4 control-label">Цветова схема на бутона</label>
									<div class="col-sm-8">
										<div class="jet_scheme_grid_wrap">
											<div class="jet_scheme_grid">
												<?php foreach ($mt_jet_button_schemes as $idx => $sch) { ?>
													<label class="jet_scheme_card<?php if ((int)$mt_jet_credit_button_scheme === $idx) { echo ' is-selected'; } ?>">
														<input type="radio" name="mt_jet_credit_button_scheme" value="<?php echo $idx; ?>" class="jet_scheme_radio" data-scheme-idx="<?php echo $idx; ?>" <?php if ((int)$mt_jet_credit_button_scheme === $idx) { echo 'checked="checked"'; } ?> />
														<span class="jet_scheme_preview" style="background: <?php echo $sch['background']; ?>; border: 2px solid <?php echo $sch['border']; ?>; border-radius: 16px;">
															<span class="jet_scheme_preview_text" style="color: <?php echo $sch['color']; ?>;"><?php echo $sch['short']; ?></span>
														</span>
													</label>
												<?php } ?>
											</div>
										</div>
										<p id="mt_jet_scheme_selected_summary" class="jet_scheme_summary">Избрана визуална схема: <?php echo $mt_jet_button_scheme_label; ?></p>
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-4 control-label" for="mt_jet_credit_btn_text">Текст на основния бутон</label>
									<div class="col-sm-8">
										<input type="text" name="mt_jet_credit_btn_text" id="mt_jet_credit_btn_text" value="<?php echo $mt_jet_credit_btn_text; ?>" class="form-control" maxlength="160" placeholder="Купи на изплащане с" />
										<div class="small">Текстът в първия ред на персонализирания бутон.</div>
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-4 control-label" for="mt_jet_credit_btn_text_card">Текст на картовия бутон</label>
									<div class="col-sm-8">
										<input type="text" name="mt_jet_credit_btn_text_card" id="mt_jet_credit_btn_text_card" value="<?php echo $mt_jet_credit_btn_text_card; ?>" class="form-control" maxlength="160" placeholder="На вноски с твоята кредитна карта" />
										<div class="small">Текстът в първия ред на бутона за кредитна карта.</div>
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-4 control-label" for="mt_jet_credit_btn_logo">Покажи лого в бутона</label>
									<div class="col-sm-8">
										<input type="hidden" name="mt_jet_credit_btn_logo" value="0" />
										<label class="mt-jet-switch">
											<input type="checkbox" name="mt_jet_credit_btn_logo" value="1" id="mt_jet_credit_btn_logo" <?php if ($mt_jet_credit_btn_logo == 1) { echo 'checked'; } ?> />
											<span class="mt-jet-slider"></span>
										</label>
										<span class="mt-jet-switch-label"><?php echo $mt_jet_credit_btn_logo == 1 ? 'Включен' : 'Изключен'; ?></span>
										<div class="text-small">Определя дали да се показва логото на ПБ Лични Финанси в персонализирания бутон.</div>
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-4 control-label" for="mt_jet_credit_btn_max_width">Максимална ширина на бутона</label>
									<div class="col-sm-8">
										<input type="number" name="mt_jet_credit_btn_max_width" id="mt_jet_credit_btn_max_width" min="30" max="1200" step="1" value="<?php echo $mt_jet_credit_btn_max_width; ?>" class="form-control" inputmode="numeric" style="max-width: 140px;" />
										<input type="range" id="mt_jet_credit_btn_max_width_range" class="form-control" min="30" max="1200" step="1" value="<?php echo $mt_jet_credit_btn_max_width; ?>" style="max-width: 100%; margin-top: 10px; height: 28px;" />
										<div class="small">Ширина в px. Може да зададете стойност ръчно или с плъзгача (30–1200).</div>
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-4 control-label" for="mt_jet_credit_btn_round">Радиус на закръгление</label>
									<div class="col-sm-8">
										<input type="number" name="mt_jet_credit_btn_round" id="mt_jet_credit_btn_round" min="0" max="25" step="1" value="<?php echo $mt_jet_credit_btn_round; ?>" class="form-control" inputmode="numeric" style="max-width: 140px;" />
										<input type="range" id="mt_jet_credit_btn_round_range" class="form-control" min="0" max="25" step="1" value="<?php echo $mt_jet_credit_btn_round; ?>" style="max-width: 100%; margin-top: 10px; height: 28px;" />
										<div class="small">Радиус в px. 0 означава бутони без закръгление. Стойност ръчно или плъзгач (0–25).</div>
									</div>
								</div>

								<div class="form-group">
									<label class="col-sm-4 control-label" for="mt_jet_credit_btn_font">Размер на шрифт в бутона</label>
									<div class="col-sm-8">
										<input type="number" name="mt_jet_credit_btn_font" id="mt_jet_credit_btn_font" min="6" max="36" step="1" value="<?php echo $mt_jet_credit_btn_font; ?>" class="form-control" inputmode="numeric" style="max-width: 140px;" />
										<input type="range" id="mt_jet_credit_btn_font_range" class="form-control" min="6" max="36" step="1" value="<?php echo $mt_jet_credit_btn_font; ?>" style="max-width: 100%; margin-top: 10px; height: 28px;" />
										<div class="small">Размер в px за текстовете в персонализирания бутон (6–36). Стойност ръчно или плъзгач.</div>
									</div>
								</div>
							</div>
						</div>

						<div class="tab-pane" id="tab-filters">
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title"><i class="fa fa-pencil"></i> Форма за въвеждане на лихвени филтри</h3>
								</div>
								<div class="panel-body" style="display:flex;flex-direction:column;gap:5px;">
									<div>
										<div class="col-md-2 col-sm-12">Продуктов ID номер. Идентификатора на даден продукт в системата на OpenCart. Id #. Филтъра действа за всички продукти ако използвате глобалния символ *:</div>
										<div class="col-md-2 col-sm-12">Избери процент оскъпяване. Избирате процента оскъпяване който да действа за филтъра. Ако изберете "Не показвай бутона", бутона няма да се показва в продуктовата страница.:</div>
										<div class="col-md-2 col-sm-12">За кои месечни вноски действа филтъра. За разделител между цифрите за месеци се използва подчертаващо тире.:</div>
										<div class="col-md-2 col-sm-12">Цена на продукта над която да се задейства този филтър. Ако е 0, филтъра ще действа за всяка цена.:</div>
										<div class="col-md-2 col-sm-12">Начало за действие на филтъра. Датата от която започва да действа този филтър.:</div>
										<div class="col-md-2 col-sm-12">Край за действие на филтъра. Датата на която приклчва действието на филтъра.:</div>
									</div>
									<div>
										<div class="col-md-2 col-sm-12">
											<input type="text" id="mt_jet_credit_product_id" class="form-control" />
										</div>
										<div class="col-md-2 col-sm-12">
											<select id="mt_jet_credit_product_percent" class="form-control">
												<option value=-1.00>Не показвай бутона</option>
												<option value=0.00 selected="selected">0.00%</option>
												<option value=0.80>0.80%</option>
												<option value=0.99>0.99%</option>
												<option value=1.00>1.00%</option>
												<option value=1.10>1.10%</option>
												<option value=1.20>1.20%</option>
												<option value=1.40>1.40%</option>
											</select>
										</div>
										<div class="col-md-2 col-sm-12">
											<input type="text" id="mt_jet_credit_product_meseci" class="form-control" />
										</div>
										<div class="col-md-2 col-sm-12">
											<input type="number" id="mt_jet_credit_product_price" class="form-control" value="0" />
										</div>
										<div class="col-md-2 col-sm-12">
											<input type="date" id="mt_jet_credit_product_start" class="form-control" />
										</div>
										<div class="col-md-2 col-sm-12">
											<input type="date" id="mt_jet_credit_product_end" class="form-control" />
										</div>
									</div>
								</div>
								<div class="panel-footer" style="display:flex;justify-content:end;">
									<button type="button" id="mt_jet_credit_btn_add" data-toggle="tooltip" title="Добави филтъра." class="btn btn-primary"><i class="fa fa-save"></i>&nbsp;Добави</button>
								</div>
							</div>

							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title"><i class="fa fa-pencil"></i> Съществуващи филтри</h3>
								</div>
								<div class="panel-body" style="display:flex;flex-direction:column;gap:5px;">
									<div>
										<div class="col-md-1 col-sm-12">Продуктов ID номер</div>
										<div class="col-md-2 col-sm-12">Процент оскъпяване</div>
										<div class="col-md-2 col-sm-12">Месечни вноски</div>
										<div class="col-md-2 col-sm-12">Цена на продуктa</div>
										<div class="col-md-2 col-sm-12">Начало</div>
										<div class="col-md-2 col-sm-12">Край</div>
										<div class="col-md-1 col-sm-12">&nbsp;</div>
									</div>
									<?php foreach ($mt_jet_schemes as $schemes) { ?>
										<div>
											<div class="col-md-1 col-sm-12">
												<input type="text" readonly class="form-control" value="<?php echo $schemes['jet_product_id']; ?>" />
											</div>
											<div class="col-md-2 col-sm-12">
												<?php if ($schemes['jet_product_percent'] == -1) { ?>
												<input type="text" readonly class="form-control" value="Не показвай бутона" />
												<?php } else { ?>
												<input type="text" readonly class="form-control" value="<?php echo $schemes['jet_product_percent']; ?>" />
												<?php } ?>
											</div>
											<div class="col-md-2 col-sm-12">
												<input type="text" readonly class="form-control" value="<?php echo $schemes['jet_product_meseci']; ?>" />
											</div>
											<div class="col-md-2 col-sm-12">
												<input type="text" readonly class="form-control" value="<?php echo $schemes['jet_product_price']; ?>" />
											</div>
											<div class="col-md-2 col-sm-12">
												<input type="date" readonly class="form-control" value="<?php echo $schemes['jet_product_start']; ?>" />
											</div>
											<div class="col-md-2 col-sm-12">
												<input type="date" readonly class="form-control" value="<?php echo $schemes['jet_product_end']; ?>" />
											</div>
											<div class="col-md-1 col-sm-12">
												<button type="button" onclick="deleteSchema('<?php echo $schemes['jet_product_id']; ?>');" data-toggle="tooltip" title="Изтрий филтъра." class="btn btn-danger"><i class="fa fa-trash"></i>&nbsp;Изтрий</button>
											</div>
										</div>
									<?php } ?>
								</div>
							</div>
						</div>

					</div>
				</div>
			</div>
		</form>
	</div>
</div>

<script>
$(document).ready(function() {
	var activeTab = localStorage.getItem('mt_jet_credit_active_tab');
	if (activeTab && $('#mt_jet_credit_tabs a[href="' + activeTab + '"]').length) {
		$('#mt_jet_credit_tabs a[href="' + activeTab + '"]').tab('show');
	}

	$('#mt_jet_credit_tabs a').on('shown.bs.tab', function(e) {
		localStorage.setItem('mt_jet_credit_active_tab', $(this).attr('href'));
	});

	function syncJetButtonTypePreview() {
		var v = $('#mt_jet_credit_button_type').val();
		if (v === 'wide') {
			$('#mt-jet-preview-standard').hide();
			$('#mt-jet-preview-wide').show();
			$('#mt-jet-wide-only-settings').show();
		} else {
			$('#mt-jet-preview-wide').hide();
			$('#mt-jet-preview-standard').show();
			$('#mt-jet-wide-only-settings').hide();
		}
	}
	$('#mt_jet_credit_button_type').on('change', syncJetButtonTypePreview);
	syncJetButtonTypePreview();

	var mtJetSchemeStyles = <?php echo $mt_jet_button_scheme_styles_for_js; ?>;
	var mtJetSchemeLabels = <?php echo $mt_jet_button_scheme_labels_json; ?>;

	function jetClampMaxWidthVal(v) {
		v = parseInt(v, 10);
		if (isNaN(v)) { v = 570; }
		if (v < 30) { v = 30; }
		if (v > 1200) { v = 1200; }
		return v;
	}
	function jetMaxWidthStyleSuffix() {
		return '--jet-wide-max-width:' + jetClampMaxWidthVal($('#mt_jet_credit_btn_max_width').val()) + 'px;';
	}
	function jetClampRoundVal(v) {
		v = parseInt(v, 10);
		if (isNaN(v)) { v = 16; }
		if (v < 0) { v = 0; }
		if (v > 25) { v = 25; }
		return v;
	}
	function jetRoundStyleSuffix() {
		return '--jet-wide-radius:' + jetClampRoundVal($('#mt_jet_credit_btn_round').val()) + 'px;';
	}
	function jetClampFontVal(v) {
		v = parseInt(v, 10);
		if (isNaN(v)) { v = 14; }
		if (v < 6) { v = 6; }
		if (v > 36) { v = 36; }
		return v;
	}
	function jetFontStyleSuffix() {
		return '--jet-wide-font-size:' + jetClampFontVal($('#mt_jet_credit_btn_font').val()) + 'px;';
	}

	function applyJetSchemePreview(idx) {
		var k = (idx === 0 || idx) ? String(idx) : '0';
		var s = (mtJetSchemeStyles && (mtJetSchemeStyles[k] || mtJetSchemeStyles[idx])) || '';
		s += jetMaxWidthStyleSuffix();
		s += jetRoundStyleSuffix();
		s += jetFontStyleSuffix();
		$('#mt-jet-wide-preview-wrap').attr('style', s);
		$('#mt-jet-wide-preview-wrap-card').attr('style', s);
		var lab = (mtJetSchemeLabels && (mtJetSchemeLabels[idx] !== undefined && mtJetSchemeLabels[idx] !== null)) ? mtJetSchemeLabels[idx] : '';
		$('#mt_jet_scheme_selected_summary').text('Избрана визуална схема: ' + lab);
	}

	$(document).on('change', '.jet_scheme_radio', function() {
		$('.jet_scheme_card').removeClass('is-selected');
		$(this).closest('.jet_scheme_card').addClass('is-selected');
		applyJetSchemePreview(parseInt($(this).val(), 10) || 0);
	});
	applyJetSchemePreview(parseInt($('.jet_scheme_radio:checked').val(), 10) || 0);

	$('#mt_jet_credit_btn_max_width, #mt_jet_credit_btn_max_width_range').on('input change', function(e) {
		var $n = $('#mt_jet_credit_btn_max_width');
		var $r = $('#mt_jet_credit_btn_max_width_range');
		var w;
		if (e && e.target && e.target.id === 'mt_jet_credit_btn_max_width_range') {
			w = jetClampMaxWidthVal($r.val());
			$n.val(w);
		} else {
			w = jetClampMaxWidthVal($n.val());
			$r.val(w);
		}
		applyJetSchemePreview(parseInt($('.jet_scheme_radio:checked').val(), 10) || 0);
	});
	$('#mt_jet_credit_btn_round, #mt_jet_credit_btn_round_range').on('input change', function(e) {
		var $n = $('#mt_jet_credit_btn_round');
		var $r = $('#mt_jet_credit_btn_round_range');
		var t;
		if (e && e.target && e.target.id === 'mt_jet_credit_btn_round_range') {
			t = jetClampRoundVal($r.val());
			$n.val(t);
		} else {
			t = jetClampRoundVal($n.val());
			$r.val(t);
		}
		applyJetSchemePreview(parseInt($('.jet_scheme_radio:checked').val(), 10) || 0);
	});
	$('#mt_jet_credit_btn_font, #mt_jet_credit_btn_font_range').on('input change', function(e) {
		var $n = $('#mt_jet_credit_btn_font');
		var $r = $('#mt_jet_credit_btn_font_range');
		var t;
		if (e && e.target && e.target.id === 'mt_jet_credit_btn_font_range') {
			t = jetClampFontVal($r.val());
			$n.val(t);
		} else {
			t = jetClampFontVal($n.val());
			$r.val(t);
		}
		applyJetSchemePreview(parseInt($('.jet_scheme_radio:checked').val(), 10) || 0);
	});

	var defaultJetBtnText = 'Купи на изплащане с';
	var defaultJetBtnTextCard = 'На вноски с твоята кредитна карта';

	function syncJetBtnTextPreview() {
		var t = ($('#mt_jet_credit_btn_text').val() || '').trim();
		$('#mt-jet-btn-text-preview').text(t === '' ? defaultJetBtnText : t);
	}
	function syncJetBtnTextCardPreview() {
		var t = ($('#mt_jet_credit_btn_text_card').val() || '').trim();
		$('#mt-jet-btn-text-card-preview').text(t === '' ? defaultJetBtnTextCard : t);
	}
	$('#mt_jet_credit_btn_text').on('input change', syncJetBtnTextPreview);
	$('#mt_jet_credit_btn_text_card').on('input change', syncJetBtnTextCardPreview);

	function syncJetBtnLogoPreview() {
		var on = $('#mt_jet_credit_btn_logo').is(':checked');
		$('#mt-jet-wide-preview-logo-main, #mt-jet-wide-preview-logo-card').toggle(on);
	}
	$('#mt_jet_credit_btn_logo').on('change', syncJetBtnLogoPreview);
});

$(document).on("change", ".mt-jet-switch input[type='checkbox']", function() {
	$(this).closest(".mt-jet-switch").next(".mt-jet-switch-label").text(this.checked ? "Включен" : "Изключен");
});

$(document).on("click","#mt_jet_credit_btn_add",function(e) {
	e.preventDefault();
	if (
		$("#mt_jet_credit_product_id").val() !== '' &&
		$("#mt_jet_credit_product_meseci").val() !== '' &&
		$("#mt_jet_credit_product_price").val() !== '' &&
		$("#mt_jet_credit_product_start").val() !== '' &&
		$("#mt_jet_credit_product_end").val() !== ''
	) {
		$.ajax({
			url : '../index.php?route=extension/mt_jet_credit/jetcredit/createSchema',
			type : 'post',
			dataType: 'json',
			data : {
				mt_jet_credit_product_id : $("#mt_jet_credit_product_id").val(),
				mt_jet_credit_product_percent: $("#mt_jet_credit_product_percent option:selected").val(),
				mt_jet_credit_product_meseci: $("#mt_jet_credit_product_meseci").val(),
				mt_jet_credit_product_price: $("#mt_jet_credit_product_price").val(),
				mt_jet_credit_product_start: $("#mt_jet_credit_product_start").val(),
				mt_jet_credit_product_end: $("#mt_jet_credit_product_end").val()
			},
			success : function( json ) {
				if (json['success'] == 'success') {
					if (json['exist'] != '') {
						alert(json['exist']);
					}else{
						alert("Успешно записахте филтъра.");
					}
					window.location.reload();
				}
			}
		});
	} else {
		alert("Имате непопълнени полета!");
	}
});

const deleteSchema = (id) => {
	$.ajax({
		url : '../index.php?route=extension/mt_jet_credit/jetcredit/deleteSchema',
		type : 'post',
		dataType: 'json',
		data : {
			mt_jet_credit_product_id : id
		},
		success : function( json ) {
			if (json['success'] == 'success'){
				alert("Успешно изтрихте филтъра.");
				window.location.reload();
			}
		}
	});
}
</script>
<?php echo $footer; ?>
