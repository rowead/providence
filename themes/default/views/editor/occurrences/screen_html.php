<?php
/* ----------------------------------------------------------------------
 * app/views/editor/occurrences/screen_html.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source occurrences management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2008-2015 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */
$t_occurrence = $this->getVar('t_subject');
$vn_occurrence_id = $this->getVar('subject_id');
$vn_above_id = $this->getVar('above_id');

$vb_can_edit = $t_occurrence->isSaveable($this->request);
$vb_can_delete = $t_occurrence->isDeletable($this->request);

$vs_rel_table = $this->getVar('rel_table');
$vn_rel_type_id = $this->getVar('rel_type_id');
$vn_rel_id = $this->getVar('rel_id');

$vs_control_box = '';
if ($vb_can_edit) {
	$vs_control_box = caFormControlBox(
		caFormSubmitButton($this->request, __CA_NAV_BUTTON_SAVE__, _t("Save"), 'OccurrenceEditorForm').' '.
		($this->getVar('show_save_and_return') ? caFormSubmitButton($this->request, __CA_NAV_BUTTON_SAVE__, _t("Save and return"), 'OccurrenceEditorForm', array('isSaveAndReturn' => true)) : '').' '.
		caNavButton($this->request, __CA_NAV_BUTTON_CANCEL__, _t("Cancel"), '', 'editor/occurrences', 'OccurrenceEditor', 'Edit/'.$this->request->getActionExtra(), ($vn_occurrence_id ? array('occurrence_id' => $vn_occurrence_id) : array('type_id' => $t_occurrence->getTypeID()))),
		'',
		((intval($vn_occurrence_id) > 0) && $vb_can_delete) ? caNavButton($this->request, __CA_NAV_BUTTON_DELETE__, _t("Delete"), '', 'editor/occurrences', 'OccurrenceEditor', 'Delete/'.$this->request->getActionExtra(), array('occurrence_id' => $vn_occurrence_id)) : ''
	);
}

$va_form_elements = $t_occurrence->getBundleFormHTMLForScreen(
	$this->request->getActionExtra(),
	array(
		'request' => $this->request,
		'formName' => 'OccurrenceEditorForm'
	),
	$va_bundle_list
);
?>
<?php print $vs_control_box; ?>
<div class="sectionBox">
	<?php print caFormTag($this->request, 'Save/'.$this->request->getActionExtra().'/occurrence_id/'.$vn_occurrence_id, 'OccurrenceEditorForm', null, 'POST', 'multipart/form-data'); ?>
		<div class="grid">
			<?php print join("\n", $va_form_elements); ?>
			<input type='hidden' name='occurrence_id' value='<?php print $vn_occurrence_id; ?>'/>
			<input type='hidden' name='above_id' value='<?php print $vn_above_id; ?>'/>
			<input id='isSaveAndReturn' type='hidden' name='is_save_and_return' value='0'/>
			<input type='hidden' name='rel_table' value='<?php print $vs_rel_table; ?>'/>
			<input type='hidden' name='rel_type_id' value='<?php print $vn_rel_type_id; ?>'/>
			<input type='hidden' name='rel_id' value='<?php print $vn_rel_id; ?>'/>
<?php
			if($this->request->getParameter('rel', pInteger)) {
?>
				<input type='hidden' name='rel' value='1'/>
<?php
			}
?>
		</div>
	</form>
</div>
<?php print $vs_control_box; ?>
<div class="editorBottomPadding"><!-- empty --></div>
<?php print caSetupEditorScreenOverlays($this->request, $t_occurrence, $va_bundle_list); ?>
