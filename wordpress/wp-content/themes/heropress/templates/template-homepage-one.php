<?php 
/**
Template Name: Homepage One
*/
get_header();

	get_template_part('sections/heropress','slider');
	get_template_part('sections/heropress','service');		
	get_template_part('sections/specia','features');
	get_template_part('sections/heropress','portfolio');
	get_template_part('sections/heropress','call-action');	
	get_template_part('sections/specia','blog');
	
get_footer();
