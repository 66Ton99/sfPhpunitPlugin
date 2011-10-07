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
  <?php foreach ($list as $key => $val) { if (null !== $keyOr) $key = $keyOr . '-' . $key; ?>
    <li>
      <?php if (!(0 == $key && !is_string($val))) { ?>
        <input <?php  if (is_string($val) && 'commObject' == dirname($val)) { 
                        ?>checked="checked" type="radio" name="comm"<?php 
                      } else { 
                        ?>type="checkbox" name="list[]" <?php 
                      } ?> value="<?php echo $key ?>" id="<?php echo $key ?>">
      <?php } ?> 
      <?php if (!is_string($val) && !empty($val[0])) { 
        echo label_for($key, dirname($val[0])); 
        writeRow($val, $key);
      } elseif (is_string($val)) { 
        echo label_for($key, basename($val)); 
      } ?> 
    </li>
  <?php } ?>
</ul>
<?php } ?>


<?php echo form_tag(sfContext::getInstance()->getModuleName() . '/load') ?>
  <?php writeRow($fixtureslist); ?>
  <?php echo submit_tag('Load', array('name' => 'load')) ?>
</form>