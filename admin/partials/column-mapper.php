<?php if (!defined('WPINC')) {
	die;
}
/** @var array $feed_options */
/** @var array $products */
/** @var array $config */
/** @var array $columns */
?>

<div id="page-loader" style="display: none;">
	<div class="table-wrapper">
		<table class="tt-table" role="presentation">
			<thead>
			<tr>
				<td>&nbsp;</td>
				<td><?php printf(esc_html__('%s Field', 'tradetracker-connect'), 'TradeTracker'); ?></td>
				<td>&nbsp;</td>
				<td><?php printf(esc_html__('%s Field', 'tradetracker-connect'), esc_html(get_bloginfo('name'))); ?></td>
				<?php foreach($products as $product): ?>
					<td><?php esc_html_e('Reference Product', 'tradetracker-connect'); ?> #<?php echo esc_html($product['id']); ?></td>
				<?php endforeach; ?>
			</tr>
			</thead>
			<tbody id="feed-mapper">
			<?php foreach($columns as $column):
				if (
					(
						($feed_options === [] && !isset($config[$column])) || // if there are no mapped columns, and the column is not suggested/required
						($feed_options !== [] && !isset($feed_options[$column])) // if there are mapped columns, and the column is not in the mapped columns
					)
					&& !(isset($config[$column]) && $config[$column] === 'required')) { // always display required columns
					continue;
				}
				?>
				<tr>
					<td class="br">
						<?php if(isset($config[$column]) && $config[$column] === 'required'): ?>
							<?php esc_html_e('Required', 'tradetracker-connect'); ?>
						<?php else: ?>
							<button type="button" class="button button-small button-remove"><?php esc_html_e('Remove', 'tradetracker-connect'); ?></button>
						<?php endif; ?>
					</td>
					<td>
						<select class="tt-field" data-selected="<?php echo esc_attr($column); ?>" <?php echo (isset($config[$column]) && $config[$column] === 'required' ? 'disabled' : ''); ?>></select>
					</td>
					<td>
						<svg style="margin-bottom: -5px;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
							<path fill-rule="evenodd" d="M10.293 15.707a1 1 0 010-1.414L14.586 10l-4.293-4.293a1 1 0 111.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z" clip-rule="evenodd" />
							<path fill-rule="evenodd" d="M4.293 15.707a1 1 0 010-1.414L8.586 10 4.293 5.707a1 1 0 011.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z" clip-rule="evenodd" />
						</svg>
					</td>
					<td class="br">
						<select class="internal-field" data-name="tradetracker_connect_feed_options" <?php echo isset($feed_options[$column]) ? 'data-selected="' . esc_attr($feed_options[$column]) . '"' : '' ?> ></select>
					</td>
					<?php foreach($products as $idx => $product): ?>
						<td class="br">
							<span class="reference-value" data-index="<?php echo esc_attr($idx); ?>"><?php esc_html_e('Select an option to preview its value', 'tradetracker-connect'); ?></span>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<p>
	<button id="add-new-row" type="button" class="button button-small"><?php esc_html_e('Add row', 'tradetracker-connect'); ?></button>
</p>
</div>

<script id="new-row" type="text/html">
<tr>
	<td class="br">
		<button type="button" class="button button-small button-remove"><?php echo esc_html_e('Remove', 'tradetracker-connect'); ?></button>
	</td>
	<td>
		<select class="tt-field"></select>
	</td>
	<td>
		<svg style="margin-bottom: -5px;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
			<path fill-rule="evenodd" d="M10.293 15.707a1 1 0 010-1.414L14.586 10l-4.293-4.293a1 1 0 111.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z" clip-rule="evenodd" />
			<path fill-rule="evenodd" d="M4.293 15.707a1 1 0 010-1.414L8.586 10 4.293 5.707a1 1 0 011.414-1.414l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0z" clip-rule="evenodd" />
		</svg>
	</td>
	<td class="br">
		<select data-name="tradetracker_connect_feed_options" class="internal-field"></select>
	</td>
   <?php foreach($products as $idx => $product): ?>
		<td class="br">
			<span class="reference-value" data-index="<?php echo esc_attr($idx); ?>"><?php esc_html_e('Select an option to preview its value', 'tradetracker-connect'); ?></span>
		</td>
	<?php endforeach; ?>
</tr>
</script>
<script>
var products = <?php echo wp_json_encode($products); ?>, feedcolumns = <?php echo wp_json_encode($columns); ?>;

/**
 * Get the product keys from the products array in dot notation
 * If there is an integer in the key, it will be replaced with a star
 * So we can use it later to match the product keys with the feed columns
 */
function getProductColumns() {
	let productColumns = [];
	for(let productKey in products) {
		let product = products[productKey];
		let keys = Object.keys(product);
		for(let i = 0; i < keys.length; i++) {
			let key = keys[i];
			if(match = key.match(/\.\d/)) {
				key = key.replace(match, '.*');
			}
			if(!productColumns.includes(key)) {
				productColumns.push(key);
			}
		}
	}
	return productColumns;
}

function getSelectOptions(data, selected = '') {
	let initSelectedAttr = (selected ? '' : ' selected');
	let output = `<option value=""${initSelectedAttr}><?php echo esc_js('Select an option', 'tradetracker-connect'); ?></option>`;
	for(let i = 0; i < data.length; i++) {
		let selectedAttr = '';
		if(selected && selected === data[i]) {
			selectedAttr = ' selected';
		}
		output += `<option value="${data[i]}"${selectedAttr}>${data[i]}</option>`;
	}
	return output;
}

jQuery(document).ready(function($) {
	// init the select fields
	$('#tradetracker-connect-feed-page select.tt-field').each(function(idx, el) {
		$(el).html(getSelectOptions(feedcolumns, $(el).attr('data-selected')));
	});
	$('#tradetracker-connect-feed-page select.internal-field').each(function(idx, el) {
		$(el).html(getSelectOptions(getProductColumns(), $(el).attr('data-selected')));
	});
	$('#page-loader').show();
	$(document).on('click', '#tradetracker-connect-feed-page .button-remove:not([disabled])', function(e) {
		let row = $(this).closest('tr');
		if($(row).find('select').val() !== '') {
			if(!confirm('<?php echo esc_js(__('Are you sure you want to remove this row?', 'tradetracker-connect')); ?>')) {
				return false;
			}
		}
		$(row).remove();
	});
	$(document).on('change', '#tradetracker-connect-feed-page select.tt-field', function(e) {
		let row = $(this).closest('tr'), value = $(this).val();
		$('#tradetracker-connect-feed-page table tbody#feed-mapper tr').each(function(idx, el) {
			if(idx === $(row).index()) {
				return;
			}
			if(!value) {
				row.find('select.internal-field').removeAttr('name');
				return;
			}
			$target = $(el).find('.tt-field');
			if(value !== '' && $target.val() === value) {
				if(typeof $target.attr('disabled') !== 'undefined') {
					// Required field
					row.find('.tt-field').val('');
				} else {
					$target.val('');
				}
			}
		});
		row.find('select.internal-field').change();
	});
	$(document).on('change', '#tradetracker-connect-feed-page select.internal-field', function(e) {
		let row = $(this).closest('tr'), value = $(this).val();
		if(value !== '') {
			// Get the reference value and preview it
			row.find('.reference-value[data-index]').each(function(idx, el) {
				// Find the product to preview what we are looking for
				let product = products[parseInt($(el).attr('data-index'))], keys = Object.keys(product), target = [];

				// There are 2 possibilities: key contains a star or key does not contain a star
				// First we check if the value has a star
				if(value.includes('*')) {
                    // If it does, use regex to swap out the star for a decimal
					let regex = new RegExp('^' + value.replace('*', '\\d') + '$');
					for(let i = 0; i < keys.length; i++) {
						let key = keys[i];
						if(key.match(regex)) {
                            target.push(lodash.escape(product[key]));
                        }
					}
                } else {
                    // If it doesn't we simply look for the value in the keys
                    for(let i = 0; i < keys.length; i++) {
                        let key = keys[i];
                        if(key === value) {
                            target.push(lodash.escape(product[key]));
                        }
                    }
                }

				if(target.join('') !== '' && target.length > 0) {
					$(el).html(`<ul class="ul-disc"><li>${target.join('</li><li>')}</li></ul>`);
                } else {
					$(el).html('<em><?php echo esc_js('Empty value', 'tradetracker-connect'); ?></em>');
                }
			});

			// After the preview values are displayed we want to set the name of the field
			// First we check if .tt-field in the row has a value
			if(row.find('select.tt-field').val() !== '') {
				let ttField = row.find('select.tt-field');
				// If we have a value, we want to set the name of the field to data-name attribute,
				// appended with the value of the .tt-field in brackets
				$(this).attr('name', $(this).attr('data-name') + '[' + $(ttField).val() + ']');
            } else {
				// if the field is empty we remove the name attribute
				$(this).removeAttr('name');
			}
		} else {
			row.find('.reference-value[data-index]').html('<?php echo esc_js('Select an option to preview its value', 'tradetracker-connect'); ?>');
		}
	});
	$(document).on('click', '#tradetracker-connect-feed-page button#add-new-row', function(e) {
		let newRow = $('#tradetracker-connect-feed-page script#new-row').html();
		$('#tradetracker-connect-feed-page table tbody').append(newRow);
		let tableRows = $('#tradetracker-connect-feed-page table tbody tr');
		$(tableRows.last()).find('select.tt-field').html(getSelectOptions(feedcolumns));
		$(tableRows.last()).find('select.internal-field').html(getSelectOptions(getProductColumns()));
	});
	// Trigger the change event on fields that have data-selected value
	$('#tradetracker-connect-feed-page select.internal-field[data-selected]').change();
});
</script>
