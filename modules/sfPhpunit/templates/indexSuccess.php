<style>
  a {text-decoration: none}
</style>

<h2>Unit tests list</h2>

<?php include_partial('tree', array('tree' => $tree, 'path' => null)) ?>


<hr />
<?php echo link_to('Clear-cache', 'sfPhpunit/cc'); ?>
<hr />
<h2>Fixture list</h2>
<?php function writeRow($list, $keyOr = null) { ?>
<ul>
  <?php foreach ($list as $key => $val) { $id = str_replace(array('/', '.'), '', $keyOr . $val); ?>
    <li>
      <?php if (is_array($val)) { 
        echo $key; 
        writeRow($val, $key . '/');
      } else { ?>
        <input type="checkbox" name="list[]" value="<?php echo $keyOr . $val ?>" id="<?php echo $id ?>">
        <?php echo content_tag('label', basename($val), array('for' => $id));?>
      <?php } ?> 
    </li>
  <?php } ?>
</ul>
<?php } ?>


<?php echo form_tag(sfContext::getInstance()->getModuleName() . '/load', array('method' => 'get')) ?>
  <?php writeRow($fixtureslist->getRawValue()); ?>
  <input id="submit_fixtures" type="submit" name="load" value="Load" />
</form>