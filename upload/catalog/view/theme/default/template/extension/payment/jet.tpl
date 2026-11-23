<style>
@import url('https://fonts.googleapis.com/css2?family=Roboto+Condensed:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&display=swap');

/* calculator panel */
.jet_panel * {
	font-family: "Roboto Condensed", sans-serif !important;
}

.jet_panel {
	display: flex !important;
	flex-direction: column !important;
	-webkit-box-sizing: border-box !important;
	-moz-box-sizing: border-box !important;
	box-sizing: border-box !important;
	border-radius: 3px !important;
	background-color: #ffffff !important;
	padding: 0px !important;
	border: 1px solid #16a34a !important;
	box-shadow: 2px 2px 10px #6ee7b7 !important;
	margin-top: 5px !important;
}
.jet_row {
	display: flex !important;
	-webkit-box-sizing: border-box !important;
	-moz-box-sizing: border-box !important;
	box-sizing: border-box !important;
	width: 100% !important;
	padding: 0px 5px !important;
}
.jet_row:first-child {
	margin-top: 5px !important;
}
.jet_column_left {
	display: flex !important;
	-webkit-box-sizing: border-box !important;
	-moz-box-sizing: border-box !important;
	box-sizing: border-box !important;
	flex: 1 !important;
	align-items: center !important;
	height: 38px !important;
	font-size: 12pt !important;
	font-weight: 500 !important;
	line-height: 14px !important;
	background-color: transparent !important;
	color: #052e16 !important;
}
@media (max-width: 767px) {
	.jet_column_left {
		font-size: 10pt !important;
		line-height: 12px !important;
	}
}
.jet_column_right {
	display: flex !important;
	-webkit-box-sizing: border-box !important;
	-moz-box-sizing: border-box !important;
	box-sizing: border-box !important;
	flex: 1 !important;
	align-items: center !important;
	justify-content: right !important;
	height: 38px !important;
}
@media (max-width: 767px) {
	.jet_column_right {
		flex-wrap: wrap !important;
		gap: 5px !important;
		height: auto !important;
	}
}
input[type="text"].jet_input_text_active, input[type="number"].jet_input_text_active{
	flex: 1 !important;
	width: 120px;
	font-weight: 600 !important;
	font-size: 14px !important;
	line-height: 16px !important;
	text-align: right;
	color: #14532d;
	border: 1px solid #15803d;
	background-color: white !important;
	margin: 0px !important;
	padding: 3px !important;
	height: 32px !important;
	-webkit-box-shadow: 0px 2px 6px 0px rgba(0,0,0,0.3) !important;
	box-shadow: 0px 2px 6px 0px rgba(0,0,0,0.3) !important;
	border-radius: 3px !important;
}
input[type="text"].jet_input_text_active:active, input[type="number"].jet_input_text_active:active, input[type="text"].jet_input_text_active:focus, input[type="number"].jet_input_text_active:focus{
	flex: 1 !important;
	width: 120px;
	font-weight: 600 !important;
	font-size: 14px !important;
	line-height: 16px !important;
	text-align: right;
	color: #14532d;
	border: 1px solid #15803d;
	background-color: white !important;
	margin: 0px !important;
	padding: 3px !important;
	height: 32px !important;
	-webkit-box-shadow: 0px 2px 6px 0px rgba(0,0,0,0.3) !important;
	box-shadow: 0px 2px 6px 0px rgba(0,0,0,0.3) !important;
	border-radius: 3px !important;
	outline: none !important;
}
input[type="text"].jet_input_text_active.jet_left, input[type="number"].jet_input_text_active.jet_left {
	text-align: left;
}
input[type="text"].jet_input_text_active.error, input[type="number"].jet_input_text_active.error {
	border: 1px solid #ED1C24;
	color: #ED1C24;
}
.jet_button_preizcisli {
	height: 32px !important;
	background-color: #166534 !important;
	border-radius: 3px !important;
	border: 0px none white !important;
	text-align: center !important;
	font-weight: 500 !important;
	font-size: 14px !important;
	line-height: 16px !important;
	padding: 0px 5px !important;
	margin-left: 3px !important;
	color: #f0fdf4 !important;
	cursor: pointer !important;
	-webkit-box-shadow: 0px 2px 4px 0px rgba(0,0,0,0.4) !important;
	box-shadow: 0px 2px 4px 0px rgba(0,0,0,0.4) !important;
	user-select: none !important;
	-moz-user-select: none !important;
	-khtml-user-select: none !important;
	-webkit-user-select: none !important;
	-o-user-select: none !important;
}
.jet_button_preizcisli:hover {
	background-color: #14532d !important;
	color: #f0fdf4 !important;
}
.jet_input_text {
	display: flex !important;
	-webkit-box-sizing: border-box !important;
	-moz-box-sizing: border-box !important;
	box-sizing: border-box !important;
	align-items: center !important;
	justify-content: end !important;
	flex: 1 !important;
	border: 1px solid #15803d !important;
	background-color: white !important;
	margin: 0px !important;
	padding: 0px 5px !important;
	height: 32px !important;
	-webkit-box-shadow: 0px 2px 6px 0px rgba(0,0,0,0.3) !important;
	box-shadow: 0px 2px 6px 0px rgba(0,0,0,0.3) !important;
	border-radius: 3px !important;
}
.jet_input_text.jet_disable {
	background-color: #EEEEEE !important;
}
.jet_input_text > div:first-child {
	display: flex !important;
	-webkit-box-sizing: border-box !important;
	-moz-box-sizing: border-box !important;
	box-sizing: border-box !important;
	align-items: center !important;
	justify-content: end !important;
	user-select: none !important;
	-moz-user-select: none !important;
	-khtml-user-select: none !important;
	-webkit-user-select: none !important;
	-o-user-select: none !important;
	flex: 1 !important;
	height: 100% !important;
	font-weight: 600 !important;
	font-size: 12px !important;
	text-align: right !important;
	color: #14532d !important;
}
.jet_input_text > div:last-child {
	display: flex !important;
	-webkit-box-sizing: border-box !important;
	-moz-box-sizing: border-box !important;
	box-sizing: border-box !important;
	align-items: center !important;
	justify-content: start !important;
	user-select: none !important;
	-moz-user-select: none !important;
	-khtml-user-select: none !important;
	-webkit-user-select: none !important;
	-o-user-select: none !important;
	height: 100% !important;
	margin-left: 2px !important;
	font-weight: 400 !important;
	font-size: 12px !important;
	text-align: right !important;
	color: #14532d !important;
}
.jet_input_text > div:last-child > span {
	padding-right: 2px !important;
}
select.jet_input_text {
	flex: 1 !important;
	font-weight: 600 !important;
	font-size: 12px !important;
	line-height: 14px !important;
	color: #14532d !important;
	border: 1px solid #15803d !important;
	background-color: white !important;
	padding: 0px 5px !important;
	margin: 0px !important;
	height: 32px !important;
	border-radius: 3px !important;
	outline: none !important;
}
select.jet_input_text:active, select.creditjet_input_text:focus{
	flex: 1 !important;
	font-weight: 600 !important;
	font-size: 12px !important;
	line-height: 14px !important;
	color: #14532d !important;
	border: 1px solid #15803d !important;
	background-color: white !important;
	padding: 0px 5px !important;
	margin: 0px !important;
	height: 32px !important;
	border-radius: 3px !important;
	outline: none !important;
}
.jet_hr{
	padding-top: 10px !important;
	width: 100% !important;
	border-top: 0px none white !important;
	border-left: 0px none white !important;
	border-right: 0px none white !important;
	border-bottom: 1px solid #01a66c !important;
}
.jet_row_footer {
	display: flex !important;
	flex-direction: column !important;
	flex: 1 !important;
	padding: 5px 10px !important;
}
input[type="checkbox"].jet_uslovia{
	border-radius: 0px !important;
	-ms-transform: scale(1.3) !important;
	-moz-transform: scale(1.3) !important;
	-webkit-transform: scale(1.3) !important;
	-o-transform: scale(1.3) !important;
	transform: scale(1.3) !important;
	padding: 10px !important;
	appearance: checkbox !important;
	-moz-appearance: checkbox !important;
	-webkit-appearance: checkbox !important;
	opacity: 1 !important;
}
.jet_uslovia_a {
	color:#01a66c !important;
	text-decoration: none !important;
	border: none !important;
	outline: none !important;
}
.jet_uslovia_a:visited {
	color:#01a66c !important;
	border: none !important;
	outline: none !important;
}
.jet_uslovia_a:focus {
	outline: none !important;
}
</style>

<div data-jet-checkout="jet" data-jet-order="<?php echo $jet_order; ?>">
<div class="jet_panel">
	<p class="text-warning" data-jet-information role="status" hidden></p>
	<div class="jet_row">
		<div class="jet_column_left">
			Първоначална вноска (<?php echo $jet_sign; ?>)
		</div>
		<div class="jet_column_right">
			<input 
				class="jet_input_text_active" 
				type="number" 
				min="0"
				step="0.01"
				id="jet_parva" 
				name="jet_parva_input"
				value=0 
			/>
			<button 
				type="button" 
				id="btn_preizcisli" 
				class="jet_button_preizcisli"
			>Преизчисли</button>
		</div>
	</div>
	<div class="jet_row">
		<div class="jet_column_left">
				Цена на стоките (<?php echo $jet_sign; ?>)
		</div>
		<div class="jet_column_right">
			<input type="hidden" id="jet_priceall_input" name="jet_priceall_input" />
				<div class="jet_input_text jet_disable">
					<div><span id="jet_priceall"></span></div>
					<div></div>
				</div>
		</div>
	</div>
	<div class="jet_row">
		<div class="jet_column_left">
			Брой погасителни вноски
		</div>
		<div class="jet_column_right">
			<select
				id="jet_vnoski" 
				name="jet_vnoski_input"
				class="jet_input_text"
			>
				<option value="3">3 месеца</option>
				<option value="6">6 месеца</option>
				<option value="9">9 месеца</option>
				<option value="10">10 месеца</option>
				<option value="12">12 месеца</option>
				<option value="15">15 месеца</option>
				<option value="18">18 месеца</option>
				<option value="24">24 месеца</option>
				<option value="30">30 месеца</option>
				<option value="36">36 месеца</option>
			</select>
		</div>
	</div>
	<div class="jet_row">
		<div class="jet_column_left">
				Общо кредит (<?php echo $jet_sign; ?>)
		</div>
		<div class="jet_column_right">
			<input type="hidden" id="jet_total_credit_price_input" name="jet_total_credit_price_input" />
				<div class="jet_input_text jet_disable">
					<div><span id="jet_total_credit_price"></span></div>
					<div></div>
				</div>
		</div>
	</div>
	<div class="jet_row">
		<div class="jet_column_left">
				Месечна вноска (<?php echo $jet_sign; ?>)
		</div>
		<div class="jet_column_right">
			<input type="hidden" id="jet_vnoska_input" name="jet_vnoska_input" />
				<div class="jet_input_text jet_disable">
					<div><span id="jet_vnoska"></span></div>
					<div></div>
				</div>
		</div>
	</div>
	<div class="jet_row">
		<div class="jet_column_left">
			Фикс ГПР (%)
		</div>
		<div class="jet_column_right">
			<input type="hidden" id="jet_gpr_input" name="jet_gpr_input" />
			<div class="jet_input_text jet_disable">
				<div><span id="jet_gpr"></span></div>
				<div></div>
			</div>
		</div>
	</div>
	<div class="jet_row">
		<div class="jet_column_left">
			ГЛП (%)
		</div>
		<div class="jet_column_right">
			<input type="hidden" id="jet_glp_input" name="jet_glp_input" />
			<div class="jet_input_text jet_disable">
				<div><span id="jet_glp"></span></div>
				<div></div>
			</div>
		</div>
	</div>
	<div class="jet_row">
		<div class="jet_column_left">
				Общо плащания (<?php echo $jet_sign; ?>)
		</div>
		<div class="jet_column_right">
			<input type="hidden" id="jet_obshto_input" name="jet_obshto_input" />
				<div class="jet_input_text jet_disable">
					<div><span id="jet_obshto"></span></div>
					<div></div>
				</div>
		</div>
	</div>
	<div class="jet_row">
		<div class="jet_column_left">
			ЕГН *
		</div>
		<div class="jet_column_right">
			<input 
				class="jet_input_text_active jet_left" 
				type="text"
				id="jet_egn"
				name="jet_egn"
				maxlength="10"
			/>
		</div>
	</div>
	<div class="jet_hr"></div>
	<div class="jet_row_footer">
		<div style="padding-bottom: 5px;">
			<input 
				type="checkbox" 
				name="jet_uslovia" 
				value="1" 
				id="jet_uslovia" 
				class="jet_uslovia"
			/>
			&nbsp;&nbsp;&nbsp;
			<a 
				href="https://www.postbank.bg/common-conditions-PFBG" 
				class="jet_uslovia_a" 
				title="Условия за кандидатстване на ПБ Лични Финанси" 
				target="_blank"
			>
				<span style="font-size: 12px;">Запознах се с условията за кандидатстване на ПБ Лични финанси</span>
			</a>
		</div>
		<div>
			<input 
				type="checkbox" 
				name="jet_uslovia1" 
				value="1" 
				id="jet_uslovia1" 
				class="jet_uslovia"
			/>
			&nbsp;&nbsp;&nbsp;
			<a 
				href="https://www.postbank.bg/Personal-Data-PFBG-retailers" 
				class="jet_uslovia_a" 
				title="Регламент (ЕС) 2016/679 от 27 април 2016 г. за защита на физическите лица по отношение на обработката на лични данни и за свободното движение на такива данни и за отмяна на Директива 95/46 / ЕО" 
				target="_blank"
			>
				<span style="font-size: 12px;">"GDPR" означава Регламент (ЕС) 2016/679 от 27 април 2016 г. за защита на физическите лица по отношение на обработката на лични данни и за свободното движение на такива данни и за отмяна на Директива 95/46 / ЕО</span>
			</a>
		</div>
	</div>
</div>
		
<div class="buttons">
	<div class="pull-right">
		<input type="button" disabled value="<?php echo $button_confirm; ?>" id="button-confirm" class="btn btn-primary" data-loading-text="<?php echo $text_loading; ?>" />
	</div>
</div>
</div>
<link rel="stylesheet" href="catalog/view/theme/default/template/extension/mt_jet_credit/creditjet_information.css">
<script src="catalog/view/theme/default/template/extension/mt_jet_credit/creditjet_information.js"></script>
<script src="catalog/view/theme/default/template/extension/mt_jet_credit/creditjet_checkout.js?ver=<?php echo filemtime(DIR_APPLICATION . 'view/theme/default/template/extension/mt_jet_credit/creditjet_checkout.js'); ?>"></script>
