<?php
/* ----------------------------------------------------------------------
 * app/printTemplates/labels/local/AZ_large.php
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2014 Whirl-i-Gig
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
 * -=-=-=-=-=- CUT HERE -=-=-=-=-=-
 * Template configuration:
 *
 * @name AZ Large Label
 * @type label
 * @pageSize a4
 * @pageOrientation landscape
 * @tables ca_objects
 * @marginLeft 10mm
 * @marginRight 10mm
 * @marginTop 10mm
 * @marginBottom 10mm
 * @horizontalGutter 0in
 * @verticalGutter 0.25in
 * @labelWidth 70mm
 * @labelHeight 40mm
 * 
 * ----------------------------------------------------------------------
 */

 	$vo_result = $this->getVar('result');	
 ?>



 <div class="labelContainer labelAZlarge" >
 	<div class="labelHeading">
 		<p>
 			Western Australian Museum<br />			
 			<strong>{{{ ^ca_list_items.family%restrictToRelationshipTypes=identification }}}<br />
 			<em>{{{ ^ca_list_items.preferred_labels%restrictToRelationshipTypes=identification ^ca_objects_x_vocabulary_terms.identificationQualifier }}}</em></strong> {{{ <ifdef code="ca_objects_x_vocabulary_terms.typeStatus"><div class="typeStatus">^ca_objects_x_vocabulary_terms.typeStatus</div></ifdef> }}}
 		</p>
 	</div>
 	<div class="labelRegNo">
 		<p>
 			<strong>WAM</strong> {{{<ifdef code="ca_objects.idno">(^ca_objects.idno)</ifdef>}}}
 		</p>	
 	</div>
 	<div class="labelDetails">
 		<ul>
		 	<li>Locality: {{{^ca_places.hierarchy.preferred_labels%maxLevelsFromBottom=3%delimiter=,_ <ifdef code="ca_objects.verbatimLocality">^ca_objects.verbatimLocality</ifdef>}}}</li>
		 	<li>GPS {{{ ^ca_objects.verbatimLatitude }}} {{{ ^ca_objects.verbatimLongitude }}}</li>
		 	<li>Station:{{{ ^ca_objects.stationNumber }}}</li>
		 	<li>Collector:{{{ ^ca_entities.preferred_labels.displayname%restrictToRelationshipTypes=collector%delimiter=_; }}}</li>
		 	<li class="right">
	 			Depth: {{{<ifdef code="ca_objects.verbatimDepth">^ca_objects.verbatimDepth</ifdef>}}}
	 			{{{<ifdef code="ca_objects.verbatimDepthTo"> to ^ca_objects.verbatimDepthTo</ifdef>}}}
	 		</li>
		 	<li>Date: {{{<ifdef code="ca_objects.eventDate"> ^ca_objects.eventDate</ifdef>}}}</li>
	 		<li class="right">Date Det.{{{<ifdef code="ca_objects_x_vocabulary_terms.dateIdentified">^ca_objects_x_vocabulary_terms.dateIdentified</ifdef>}}}</li>
	 		<li>Det. by {{{<ifdef code="ca_objects_x_vocabulary_terms.identifiedBy"> ^ca_objects_x_vocabulary_terms.identifiedBy</ifdef>}}}</li>
			<li class="center">{{{^ca_objects.eventName}}}</li>
 		</ul>
 	</div>
 </div>
