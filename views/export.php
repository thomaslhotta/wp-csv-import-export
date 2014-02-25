<?php 
  $ps = $this->get_plugin_slug();
?>

<form id="export" method="post">  

<?php 
	foreach ( $groups as $name => $group  ) {
		$group->display();
	}
	
?>

</form>

<p class="submit">
	<button id="export-csv" name="submit-csv" class="button" type="submit">
		<?php _e( 'Export', $ps )?>
	</button>
</p>
<div id="ajax-progress"></div>