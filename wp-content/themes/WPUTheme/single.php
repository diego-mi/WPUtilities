<?php
include dirname( __FILE__ ) . '/z-protect.php';
get_header();
the_post();
?>
<div class="main-content">
    <?php get_template_part( 'loop' ); ?>
    <?php include get_template_directory() . '/tpl/single/pagination-single.php'; ?>
</div>
<?php
get_footer();
