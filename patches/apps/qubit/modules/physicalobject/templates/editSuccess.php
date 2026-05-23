<?php decorate_with('layout_1col.php'); ?>

<?php slot('title'); ?>
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      <?php echo render_title($resource); ?>
    </h1>
    <span class="small" id="heading-label">
      <?php echo __('Edit %1%', ['%1%' => sfConfig::get('app_ui_label_physicalobject')]); ?>
    </span>
  </div>
<?php end_slot(); ?>

<?php slot('content'); ?>

  <?php echo $form->renderGlobalErrors(); ?>

  <?php if (isset($sf_request->getAttribute('sf_route')->resource)) { ?>
    <?php echo $form->renderFormTag(url_for([$resource, 'module' => 'physicalobject', 'action' => 'edit']), ['id' => 'editForm']); ?>
  <?php } else { ?>
    <?php echo $form->renderFormTag(url_for(['module' => 'physicalobject', 'action' => 'add']), ['id' => 'editForm']); ?>
  <?php } ?>

    <?php echo $form->renderHiddenFields(); ?>

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="edit-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#edit-collapse" aria-expanded="false" aria-controls="edit-collapse">
            <?php echo __('Edit %1%', ['%1%' => sfConfig::get('app_ui_label_physicalobject')]); ?>
          </button>
        </h2>
        <div id="edit-collapse" class="accordion-collapse collapse" aria-labelledby="edit-heading">
          <div class="accordion-body">
            <?php echo render_field($form->name, $resource); ?>
            <?php echo render_field($form->location, $resource); ?>
            <?php echo render_field($form->type); ?>
          </div>
        </div>
      </div>
    </div>

    <?php
    // heratio#145 — AHG strongroom assignment pointer. Read-only here; the
    // actual picker lives at /strongroom/<slug>. Single small block, no
    // sfForm extension. Fails soft when the AHG tables are absent.
    if (isset($sf_request->getAttribute('sf_route')->resource)) {
        try {
            $srRow = QubitPdo::fetchOne(
                'SELECT sr.slug AS slug, sr.name AS name, ps.size_units_used AS size_used, sr.capacity_unit AS unit'
                .' FROM ahg_physical_object_storage ps'
                .' INNER JOIN ahg_strongroom sr ON sr.id = ps.strongroom_id'
                .' WHERE ps.physical_object_id = ?',
                [$resource->id]
            );
            echo '<div class="card mb-3"><div class="card-body py-2 small">'
                .'<strong>'.__('Strongroom').':</strong> ';
            if ($srRow) {
                echo link_to($srRow->name, ['module' => 'strongroom', 'action' => 'show', 'slug' => $srRow->slug])
                    .' &middot; '.__('size used').': '.htmlspecialchars((string) $srRow->size_used).' '
                    .htmlspecialchars((string) $srRow->unit)
                    .' &middot; '
                    .link_to(__('Manage'), ['module' => 'strongroom', 'action' => 'show', 'slug' => $srRow->slug]);
            } else {
                echo '<span class="text-muted">'.__('Not assigned to a strongroom.').'</span> '
                    .link_to(__('Browse strongrooms'), ['module' => 'strongroom', 'action' => 'browse']);
            }
            echo '</div></div>';
        } catch (Throwable $e) { /* ahg tables absent on vanilla AtoM — silent skip */ }
    }
    ?>

    <ul class="actions mb-3 nav gap-2">
      <?php if (null !== $next = $form->getValue('next')) { ?>
        <li><?php echo link_to(__('Cancel'), $next, ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); ?></li>
      <?php } elseif (isset($sf_request->getAttribute('sf_route')->resource)) { ?>
        <li><?php echo link_to(__('Cancel'), [$resource, 'module' => 'physicalobject'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); ?></li>
      <?php } else { ?>
        <li><?php echo link_to(__('Cancel'), ['module' => 'physicalobject', 'action' => 'browse'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); ?></li>
      <?php } ?>
      <li><input class="btn atom-btn-outline-success" type="submit" value="<?php echo __('Save'); ?>"></li>
    </ul>

  </form>

<?php end_slot(); ?>
