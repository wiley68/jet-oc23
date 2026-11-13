<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">

	<div class="page-header">
		<div class="container-fluid">
			<div class="pull-right">
				<button type="submit" form="form-jet-card" data-toggle="tooltip" title="Запиши" class="btn btn-primary"><i class="fa fa-save"></i></button>
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
		<?php if ($error_warning) { ?>
		<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
			<button type="button" class="close" data-dismiss="alert">&times;</button>
		</div>
		<?php } ?>
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><i class="fa fa-pencil"></i> Редакция</h3>
			</div>
			<div class="panel-body">
				<form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-jet-card" class="form-horizontal">
					<div class="form-group">
						<label class="col-sm-2 control-label" for="input-total"><span data-toggle="tooltip" title="Сума която трябва да бъде надвишена за да се задейства този метод на плащане. Оставете празно ако няма такава.">Минимална сума</span></label>
						<div class="col-sm-10">
							<input type="text" name="jet_card_total" value="<?php echo $jet_card_total; ?>" placeholder="Минимална сума" id="input-total" class="form-control" />
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label" for="input-order-status">Състояние на поръчката</label>
						<div class="col-sm-10">
							<select name="jet_card_order_status_id" id="input-order-status" class="form-control">
							<?php foreach ($order_statuses as $order_status) { ?>
								<?php if ($order_status['order_status_id'] == $jet_card_order_status_id) { ?>
								<option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
								<?php } else { ?>
								<option value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
								<?php } ?>
							<?php } ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label" for="input-geo-zone">Зона</label>
						<div class="col-sm-10">
							<select name="jet_card_geo_zone_id" id="input-geo-zone" class="form-control">
								<option value="0"><?php echo $text_all_zones; ?></option>
								<?php foreach ($geo_zones as $geo_zone) { ?>
								<?php if ($geo_zone['geo_zone_id'] == $jet_card_geo_zone_id) { ?>
								<option value="<?php echo $geo_zone['geo_zone_id']; ?>" selected="selected"><?php echo $geo_zone['name']; ?></option>
								<?php } else { ?>
								<option value="<?php echo $geo_zone['geo_zone_id']; ?>"><?php echo $geo_zone['name']; ?></option>
								<?php } ?>
								<?php } ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label" for="input-status">Състояние</label>
						<div class="col-sm-10">
							<select name="jet_card_status" id="input-status" class="form-control">
								<?php if ($jet_card_status) { ?>
								<option value="1" selected="selected"><?php echo $text_enabled; ?></option>
								<option value="0"><?php echo $text_disabled; ?></option>
								<?php } else { ?>
								<option value="1"><?php echo $text_enabled; ?></option>
								<option value="0" selected="selected"><?php echo $text_disabled; ?></option>
								<?php } ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label" for="input-sort-order">Подредба</label>
						<div class="col-sm-10">
							<input type="text" name="jet_card_sort_order" value="<?php echo $jet_card_sort_order; ?>" placeholder="Подредба" id="input-sort-order" class="form-control" />
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
<?php echo $footer; ?> 
