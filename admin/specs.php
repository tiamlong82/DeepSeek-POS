<?php
// File marker
require_once __DIR__ . '/../includes/admin_header.php';
$db = getDB();
$dishId = isset($_GET['dish_id']) ? intval($_GET['dish_id']) : 0;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $dishId = intval($_POST['dish_id'] ?? 0);

    try {
        if ($action === 'add_spec_option') {
            $stmt = $db->prepare("INSERT INTO dish_spec_options (dimension_id, dish_id, value_zh, price, is_default) VALUES (?,?,?,?,?)");
            $stmt->execute([$_POST['dimension_id'], $_POST['dish_id'], $_POST['value_zh'], floatval($_POST['price'] ?? 0), intval($_POST['is_default'] ?? 0)]);
        }
        elseif ($action === 'delete_spec_option') {
            $db->prepare("DELETE FROM dish_spec_options WHERE id=?")->execute([$id]);
        }
        elseif ($action === 'add_addon') {
            $stmt = $db->prepare("INSERT INTO dish_addons (dish_id, name_zh, name_en, price) VALUES (?,?,?,?)");
            $stmt->execute([$_POST['dish_id'], $_POST['name_zh'], $_POST['name_en'] ?? '', floatval($_POST['price'] ?? 0)]);
        }
        elseif ($action === 'delete_addon') {
            $db->prepare("DELETE FROM dish_addons WHERE id=?")->execute([$id]);
        }
        echo '<script>location.href="specs.php?dish_id=' . $dishId . '&ok=1";</script>';
        exit;
    } catch (Exception $e) {
        echo '<script>alert("Error: ' . addslashes($e->getMessage()) . '");</script>';
    }
}
// Load data
$dishes = $db->query("SELECT d.id, d.name_zh, c.name_zh AS cat FROM dishes d JOIN categories c ON c.id=d.category_id WHERE d.store_id=1 ORDER BY d.sort_order")->fetchAll();
$dimensions = $db->query("SELECT * FROM dish_spec_dimensions WHERE store_id=1 ORDER BY sort_order")->fetchAll();
?>
<h2>Select Dish</h2>
<form method="get" class="mb-4">
<select name="dish_id" onchange="this.form.submit()" class="form-control" style="max-width:400px">
<option value="">-- Select Dish --</option>
<?php foreach ($dishes as $d): ?>
<option value="<?=$d['id']?>" <?=$dishId==$d['id']?'selected':''?>><?=h($d['name_zh'])?> (<?=h($d['cat'])?>)</option>
<?php endforeach; ?>
</select>
</form>

<?php if ($dishId > 0):
$dishName = '';
foreach ($dishes as $d) { if ($d['id'] == $dishId) { $dishName = $d['name_zh']; break; } }
$specs = $db->query("SELECT dso.*, dsd.name_zh AS dim_name FROM dish_spec_options dso JOIN dish_spec_dimensions dsd ON dsd.id=dso.dimension_id WHERE dso.dish_id=$dishId ORDER BY dsd.sort_order, dso.sort_order")->fetchAll();
$addons = $db->query("SELECT * FROM dish_addons WHERE dish_id=$dishId ORDER BY sort_order")->fetchAll();
$groups = [];
foreach ($specs as $s) { $groups[$s['dim_name']][] = $s; }
?>
<h3><?=h($dishName)?> - Spec Options</h3>
<?php foreach ($groups as $dim => $opts): ?>
<table class="table"><thead><tr><th><?=h($dim)?></th><th>Price</th><th>Default</th><th></th></tr></thead><tbody>
<?php foreach ($opts as $o): ?>
<tr><td><?=h($o['value_zh'])?></td><td>RM<?=number_format($o['price'],2)?></td><td><?=$o['is_default']?'Yes':''?></td>
<td><form method="post" style="display:inline"><input type="hidden" name="action" value="delete_spec_option"><input type="hidden" name="id" value="<?=$o['id']?>"><input type="hidden" name="dish_id" value="<?=$dishId?>"><button class="btn-sm btn-danger" onclick="return confirm('Delete?')">X</button></form></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endforeach; ?>

<form method="post" class="form-inline mb-4 p-3 bg-light rounded">
<h4>Add Spec Option</h4>
<input type="hidden" name="action" value="add_spec_option">
<input type="hidden" name="dish_id" value="<?=$dishId?>">
<select name="dimension_id" required class="form-control">
<?php foreach ($dimensions as $dim): ?>
<option value="<?=$dim['id']?>"><?=h($dim['name_zh'])?></option>
<?php endforeach; ?>
</select>
<input type="text" name="value_zh" placeholder="Value (zh)" required class="form-control">
<input type="number" name="price" placeholder="Price" step="0.5" class="form-control" style="width:100px">
<label><input type="checkbox" name="is_default" value="1"> Default</label>
<button class="btn btn-primary">Add</button>
</form>

<h3>Add Ons</h3>
<table class="table"><thead><tr><th>Name</th><th>Price</th><th></th></tr></thead><tbody>
<?php foreach ($addons as $a): ?>
<tr><td><?=h($a['name_zh'])?></td><td>RM<?=number_format($a['price'],2)?></td>
<td><form method="post" style="display:inline"><input type="hidden" name="action" value="delete_addon"><input type="hidden" name="id" value="<?=$a['id']?>"><input type="hidden" name="dish_id" value="<?=$dishId?>"><button class="btn-sm btn-danger">X</button></form></td></tr>
<?php endforeach; ?>
</tbody></table>

<form method="post" class="form-inline p-3 bg-light rounded">
<h4>Add Add On</h4>
<input type="hidden" name="action" value="add_addon">
<input type="hidden" name="dish_id" value="<?=$dishId?>">
<input type="text" name="name_zh" placeholder="Name (zh)" required class="form-control">
<input type="text" name="name_en" placeholder="Name (en)" class="form-control">
<input type="number" name="price" placeholder="Price" step="0.5" required class="form-control" style="width:100px">
<button class="btn btn-primary">Add</button>
</form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
