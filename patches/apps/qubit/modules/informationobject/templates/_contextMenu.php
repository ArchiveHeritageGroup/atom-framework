<?php if ($sf_user->getAttribute('search-realm') && sfConfig::get('app_enable_institutional_scoping')) { ?>
  <?php include_component('repository', 'holdingsInstitution', ['resource' => QubitRepository::getById($sf_user->getAttribute('search-realm'))]); ?>
<?php } else { ?>
  <?php echo get_component('repository', 'logo'); ?>
<?php } ?>

<?php echo get_component('informationobject', 'treeView'); ?>

<?php
// The staticPagesMenu card is deliberately not rendered here.
//
// It is a site-wide list - on a stock install the node is empty, so nothing shows
// and nobody notices it is wired into the record sidebar at all. As soon as a
// plugin contributes an entry (feedback, favourites) the card appears above every
// archival description, where a general site link is noise rather than context.
// The record sidebar is for this record: its institution and its place in the
// hierarchy.
//
// The card still renders where it belongs, on the landing page
// (staticpage/homeSuccess) and on static pages (staticpage/indexSuccess).
?>
