<?php

/**
 * Goes through an array (which may also contain other arrays) and find
 * the most latest 'created_at' value.
 */
function find_latest_created_at($a, $prefix = false) {
	if (!is_array($a))
		return false;
	$created_at = false;
	foreach ($a as $k => $v) {
		if (!is_numeric($k) && $k == "created_at") {
			$created_at = max($created_at, strtotime($v));
		} else if (is_array($v)) {
			if (!$prefix || substr($k, 0, strlen($prefix)) == $prefix) {
				$created_at = max($created_at, find_latest_created_at($v));
			}
		}
	}
	return $created_at;
}

/**
 * Renders a particular graph.
 */
function render_graph($graph) {

	$graph_types = graph_types();
	if (!isset($graph_types[$graph['graph_type']])) {
		// let's not crash with an exception, let's just display an error
		render_graph_controls($graph);
		render_text($graph, "Unknown graph type " . htmlspecialchars($graph['graph_type']));
		log_uncaught_exception(new GraphException("Unknown graph type " . htmlspecialchars($graph['graph_type'])));
		return;
	}
	$graph_type = $graph_types[$graph['graph_type']];

	echo "<h2>" . htmlspecialchars(isset($graph_type['heading']) ? $graph_type['heading'] : $graph_type['title']) . "</h2>\n";
	render_graph_controls($graph);

	$add_more_currencies = "<a href=\"" . htmlspecialchars(url_for('user')) . "\">Add more currencies</a>";

	switch ($graph['graph_type']) {

		case "btc_equivalent":
			// a pie chart

			// get all balances
			$balances = get_all_summary_instances();
			$graph['last_updated'] = find_latest_created_at($balances, "total");

			// and convert them using the most recent rates
			$rates = get_all_recent_rates();

			// create data
			$data = array();
			if (isset($balances['totalbtc']) && $balances['totalbtc']['balance'] != 0) {
				$data['BTC'] = graph_number_format($balances['totalbtc']['balance']);
			}
			if (isset($balances['totalltc']) && $balances['totalltc']['balance'] != 0 && isset($rates['btcltc'])) {
				$data['LTC'] = graph_number_format($balances['totalltc']['balance'] * $rates['btcltc']['sell']);
			}
			if (isset($balances['totalnmc']) && $balances['totalnmc']['balance'] != 0 && isset($rates['btcnmc'])) {
				$data['NMC'] = graph_number_format($balances['totalnmc']['balance'] * $rates['btcnmc']['sell']);
			}
			if (isset($balances['totalusd']) && $balances['totalusd']['balance'] != 0 && isset($rates['usdbtc']) && $rates['usdbtc'] /* no div by 0 */) {
				$data['USD'] = graph_number_format($balances['totalusd']['balance'] / $rates['usdbtc']['buy']);
			}
			if (isset($balances['totalnzd']) && $balances['totalnzd']['balance'] != 0 && isset($rates['nzdbtc']) && $rates['nzdbtc'] /* no div by 0 */) {
				$data['NZD'] = graph_number_format($balances['totalnzd']['balance'] / $rates['nzdbtc']['buy']);
			}

			if ($data) {
				render_pie_chart($graph, $data, 'Currency', 'BTC');
			} else {
				render_text($graph, "Either you have not specified any accounts or addresses, or these addresses and accounts have not yet been updated.
					<br><a href=\"" . htmlspecialchars(url_for('accounts')) . "\">Add accounts and addresses</a>");
			}
			break;

		case "mtgox_btc_table":
			// a table of just BTC/USD rates
			$rates = get_all_recent_rates();
			$graph['last_updated'] = find_latest_created_at($rates['usdbtc']);

			$data = array(
				array('Buy', currency_format('usd', $rates['usdbtc']['buy'], 4)),
				array('Sell', currency_format('usd', $rates['usdbtc']['sell'], 4)),
			);

			render_table_vertical($graph, $data);
			break;

		case "balances_table":
			// a table of each currency
			// get all balances
			$balances = get_all_summary_instances();
			$summaries = get_all_summary_currencies();
			$currencies = get_all_currencies();
			$graph['last_updated'] = find_latest_created_at($balances, "total");

			// create data
			$data = array();
			foreach ($currencies as $c) {
				if (isset($summaries[$c])) {
					$balance = isset($balances['total'.$c]) ? $balances['total'.$c]['balance'] : 0;
					$data[] = array(strtoupper($c), currency_format($c, $balance, 4));
				}
			}

			$graph["extra"] = $add_more_currencies;
			render_table_vertical($graph, $data);
			break;

		case "fiat_converted_table":
			// a table of each all2fiat value
			// get all balances
			$balances = get_all_summary_instances();
			$summaries = get_all_summary_currencies();
			$currencies = get_all_currencies();
			$graph['last_updated'] = find_latest_created_at($balances, "all2");

			// create data
			$data = array();
			foreach ($currencies as $c) {
				if (isset($summaries[$c])) {
					$key = $summaries[$c];
					$key1 = "all2" . substr($key, strlen("summary_"));
					$balance = isset($balances[$key1]['balance']) ? $balances[$key1]['balance'] : 0;
					$summary_types = get_summary_types();
					$data[] = array($summary_types[$key]['short_title'], currency_format($c, $balance, 4));
				}
			}

			$graph["extra"] = $add_more_currencies;
			render_table_vertical($graph, $data);
			break;

		case "balances_offset_table":
			// a table of each currency, along with an offset field
			$balances = get_all_summary_instances();
			$summaries = get_all_summary_currencies();
			$offsets = get_all_offset_instances();
			$currencies = get_all_currencies();
			$graph['last_updated'] = find_latest_created_at($balances, "total");

			// create data
			$data = array();
			$data[] = array('Currency', 'Balance', 'Offset', 'Total');
			foreach ($currencies as $c) {
				if (isset($summaries[$c])) {
					$balance = isset($balances['total'.$c]) ? $balances['total'.$c]['balance'] : 0;
					$offset = isset($offsets[$c]) ? $offsets[$c]['balance'] : 0;
					$data[] = array(
						strtoupper($c),
						currency_format($c, $balance - $offset, 4),
						// HTML quirk: "When there is only one single-line text input field in a form, the user agent should accept Enter in that field as a request to submit the form."
						'<form action="' . htmlspecialchars(url_for('set_offset', array('page' => $graph['page_id']))) . '" method="post">' .
							'<input type="text" name="' . htmlspecialchars($c) . '" value="' . htmlspecialchars($offset == 0 ? '' : $offset) . '" maxlength="32">' .
							'</form>',
						currency_format($c, $balance /* balance includes offset */, 4),
					);
				}
			}

			$graph['extra'] = $add_more_currencies;

			render_table_horizontal_vertical($graph, $data);
			break;

		case "linebreak":
			// implemented by profile.php
			break;

		default:
			// ticker graphs are generated programatically
			if (substr($graph['graph_type'], -strlen("_daily")) == "_daily") {
				$split = explode("_", $graph['graph_type']);
				if (count($split) == 3 && strlen($split[1]) == 6) {
					// checks
					$exchange_pairs = get_exchange_pairs();
					if (isset($exchange_pairs[$split[0]])) {
						// we won't check pairs, we just won't get any data
						render_ticker_graph($graph, $split[0], substr($split[1], 0, 3), substr($split[1], 3));
						break;
					}
				}

				// total summary graphs are generated programatically
				if (substr($graph['graph_type'], 0, strlen("total_")) == "total_") {
					$split = explode("_", $graph['graph_type']);
					if (count($split) == 3 && strlen($split[1]) == 3) {
						// we will assume that it is the current user
						// (otherwise it might be possible to view other users' data)
						render_summary_graph($graph, 'total' . $split[1], $split[1], user_id());
						break;
					}
				}

				if (substr($graph['graph_type'], 0, strlen("all2")) == "all2") {
					$split = explode("_", $graph['graph_type']);
					if (count($split) == 2 && strlen($split[0]) == strlen("all2") + 3) {
						$cur = substr($split[0], strlen("all2"));
						// e.g. all2nzd

						// we will assume that it is the current user
						// (otherwise it might be possible to view other users' data)
						render_summary_graph($graph, 'all2' . $cur, $cur, user_id());
						break;
					} else if (count($split) == 3 && strlen($split[0]) == strlen("all2") + 3) {
						$cur = substr($split[0], strlen("all2"));
						// e.g. all2usd_mtgox

						// we will assume that it is the current user
						// (otherwise it might be possible to view other users' data)
						render_summary_graph($graph, 'all2' . $cur . '_' . $split[1], $cur, user_id());
						break;
					}
				}

			}

			// composition charts
			if (substr($graph['graph_type'], 0, strlen("composition_")) == "composition_") {
				$split = explode("_", $graph['graph_type']);
				if (count($split) == 3 && strlen($split[1]) == 3 && in_array($split[1], get_all_currencies())) {
					// get data
					// TODO could probably cache this
					$currency = $split[1];
					$q = db()->prepare("SELECT SUM(balance) AS balance, exchange, MAX(created_at) AS created_at FROM balances WHERE user_id=? AND is_recent=1 AND currency=? GROUP BY exchange");
					$q->execute(array(user_id(), $currency));
					$balances = $q->fetchAll();

					// need to also get address balances
					$summary_balances = get_all_summary_instances();

					// create data
					$data = array();
					if (isset($summary_balances['blockchain' . $currency]) && $summary_balances['blockchain' . $currency]['balance'] != 0) {
						$balances[] = array(
							"balance" => $summary_balances['blockchain' . $currency]['balance'],
							"exchange" => "blockchain",
							"created_at" => $summary_balances['blockchain' . $currency]['created_at'],
						);
					}
					if (isset($summary_balances['offsets' . $currency]) && $summary_balances['offsets' . $currency]['balance'] != 0) {
						$balances[] = array(
							"balance" => $summary_balances['offsets' . $currency]['balance'],
							"exchange" => "offsets",
							"created_at" => $summary_balances['offsets' . $currency]['created_at'],
						);
					}

					$graph['last_updated'] = find_latest_created_at($balances);

					if ($split[2] == "pie") {
						$data = array();
						foreach ($balances as $b) {
							if ($b['balance'] != 0) {
								$data[get_exchange_name($b['exchange'])] = $b['balance'];
							}
						}

						if ($data) {
							render_pie_chart($graph, $data, 'Source', strtoupper($currency));
						} else {
							render_text($graph, "Either you have not specified any accounts or addresses in " . get_currency_name($currency) . ", or these addresses and accounts have not yet been updated.
								<br><a href=\"" . htmlspecialchars(url_for('accounts')) . "\">Add accounts and addresses</a>");
						}
						break;
					}
				}
			}

			// let's not throw an exception, let's just render an error message
			render_text($graph, "Couldn't render graph type " . htmlspecialchars($graph['graph_type']));
			log_uncaught_exception(new GraphException("Couldn't render graph type " . htmlspecialchars($graph['graph_type'])));
			return;
	}

}

function render_ticker_graph($graph, $exchange, $cur1, $cur2) {

	$q = db()->prepare("SELECT * FROM ticker WHERE is_daily_data=1 AND exchange=:exchange AND
			currency1=:currency1 AND currency2=:currency2 ORDER BY created_at DESC LIMIT 45"); // TODO add days_to_display as parameter
	$q->execute(array(
		'exchange' => $exchange,
		'currency1' => $cur1,
		'currency2' => $cur2,
	));
	$data = array();
	$data[] = array("Date", strtoupper($cur1) . "/" . strtoupper($cur2) . " Buy", strtoupper($cur1) . "/" . strtoupper($cur2) . " Sell");
	$last_updated = false;
	while ($ticker = $q->fetch()) {
		$data[] = array(
			'new Date(' . date('Y, n-1, j', strtotime($ticker['created_at'])) . ')',
			graph_number_format($ticker['buy']),
			graph_number_format($ticker['sell']),
		);
		$last_updated = max($last_updated, strtotime($ticker['created_at']));
	}

	$graph['last_updated'] = $last_updated;
	render_linegraph_date($graph, $data);

}

function render_summary_graph($graph, $summary_type, $currency, $user_id) {

	$q = db()->prepare("SELECT * FROM summary_instances WHERE is_daily_data=1 AND summary_type=:summary_type AND
			user_id=:user_id ORDER BY created_at DESC LIMIT 45"); // TODO add days_to_display as parameter
	$q->execute(array(
		'summary_type' => $summary_type,
		'user_id' => $user_id,
	));
	$data = array();
	$data[] = array("Date", strtoupper($currency));
	$last_updated = false;
	while ($ticker = $q->fetch()) {
		$data[] = array(
			'new Date(' . date('Y, n-1, j', strtotime($ticker['created_at'])) . ')',
			graph_number_format($ticker['balance']),
		);
		$last_updated = max($last_updated, strtotime($ticker['created_at']));
	}

	$graph['last_updated'] = $last_updated;
	if (count($data) > 1) {
		render_linegraph_date($graph, $data);
	} else {
		render_text($graph, "Either you have not enabled this currency, or your summaries for this currency have not yet been updated.
					<br><a href=\"" . htmlspecialchars(url_for('user')) . "\">Configure currencies</a>");
	}

}

// a simple alias
function graph_number_format($n) {
	return number_format($n, 4, '.', '');
}

/**
 * Get all of the defined graph types. Used for display and validation.
 */
function graph_types() {
	$data = array(
		'btc_equivalent' => array('title' => 'Equivalent BTC balances (pie)', 'heading' => 'Equivalent BTC', 'description' => 'A pie chart representing the overall value of all accounts if they were all converted into BTC.<p>Exchanges used: BTC-e for LTC/NMC, Mt.Gox for USD, BitNZ for NZD'),
		'mtgox_btc_table' => array('title' => 'Mt.Gox USD/BTC (table)', 'heading' => 'Mt.Gox', 'description' => 'A simple table displaying the current buy/sell USD/BTC price.'),
		'balances_table' => array('title' => 'Total balances (table)', 'heading' => 'Total balances', 'description' => 'A table displaying the current sum of all your currencies (before any conversions).'),
		'fiat_converted_table' => array('title' => 'Converted fiat balances (table)', 'heading' => 'Converted fiat', 'description' => 'A table displaying the equivalent value of all cryptocurrencies - and not other fiat currencies - if they were immediately converted into fiat currencies via BTC.<p>Exchanges used: BTC-e for LTC/NMC, Mt.Gox for USD, BitNZ for NZD'),
		'balances_offset_table' => array('title' => 'Total balances with offsets (table)', 'heading' => 'Total balances', 'description' => 'A table displaying the current sum of all currencies (before any conversions), along with text fields to set offset values for each currency directly.'),
	);

	$summaries = get_all_summary_currencies();
	$conversions = get_all_conversion_currencies();

	// we can generate a list of daily graphs from all of the exchanges that we support
	// but we'll only want to display currency pairs that we're interested in
	foreach (get_exchange_pairs() as $key => $pairs) {
		foreach ($pairs as $pair) {
			$pp = strtoupper($pair[0]) . "/" . strtoupper($pair[1]);
			$data[$key . "_" . $pair[0] . $pair[1] . "_daily"] = array(
				'title' => get_exchange_name($key) . " historical $pp (graph)",
				'heading' => get_exchange_name($key) . " $pp",
				'description' => "A line graph displaying the historical buy/sell values for $pp on " . get_exchange_name($key) . ".",
				'hide' => !(isset($summaries[$pair[0]]) && isset($summaries[$pair[1]]))
			);
		}
	}

	// we can generate a list of summary daily graphs from all the currencies that we support
	foreach (get_summary_types() as $key => $summary) {
		$cur = $summary['currency'];
		$data["total_" . $cur . "_daily"] = array(
			'title' => "Total " . get_currency_name($cur) . " historical (graph)",
			'heading' => "Total " . strtoupper($cur),
			'description' => "A line graph displaying the historical sum of your " . get_currency_name($cur) . " (before any conversions)",
			'hide' => !isset($summaries[$cur]),
		);
	}

	foreach (get_total_conversion_summary_types() as $key => $summary) {
		$cur = $summary['currency'];
		$data["all2" . $key . "_daily"] = array(
			'title' => 'Converted ' . $summary['title'] . " historical (graph)",
			'heading' => 'Converted ' . $summary['short_title'],
			'description' => "A line graph displaying the historical equivalent value of all cryptocurrencies - and not other fiat currencies - if they were immediately converted to " . $summary['title'] . ".",
			'hide' => !isset($conversions['summary_' . $key]),
		);
	}

	// we can generate a list of composition graphs from all of the currencies that we support
	foreach (get_all_currencies() as $currency) {
		$data["composition_" . $currency . "_pie"] = array(
			'title' => "Total " . get_currency_name($currency) . " balance composition (pie)",
			'heading' => "Total " . strtoupper($currency),
			'description' => "A pie chart representing all of the sources of your total " . get_currency_name($currency) . " balance (before any conversions).",
			'hide' => !isset($summaries[$cur]),
		);
	}

	$data['linebreak'] = array('title' => 'Line break', 'description' => 'Forces a line break at a particular location. Select \'Enable layout editing\' to move it.');
	return $data;
}

function render_text($graph, $text) {
	$graph_id = htmlspecialchars($graph['id']);
	render_graph_last_updated($graph);
?>
<div id="graph_<?php echo $graph_id; ?>"<?php echo get_dimensions($graph); ?>>
<?php echo $text; ?>
<?php if (isset($graph['extra'])) echo '<div class="graph_extra">' . $graph['extra'] . '</div>'; ?>
</div>
<?php
}

function render_table_vertical($graph, $data) {
	$graph_id = htmlspecialchars($graph['id']);
	render_graph_last_updated($graph);
?>
<div id="graph_<?php echo $graph_id; ?>"<?php echo get_dimensions($graph); ?>>
<table class="standard">
<?php foreach ($data as $row) {
	echo "<tr>";
	foreach ($row as $i => $item) {
		echo ($i == 0 ? "<th>" : "<td>");
		echo $item;	// assumed to be html escaped
		echo ($i == 0 ? "</th>" : "</td>");
	}
	echo "</tr>\n";
}
?>
</table>
<?php if (isset($graph['extra'])) echo '<div class="graph_extra">' . $graph['extra'] . '</div>'; ?>
</div>
<?php
}

function render_table_horizontal_vertical($graph, $data) {
	$graph_id = htmlspecialchars($graph['id']);
	render_graph_last_updated($graph);
?>
<div id="graph_<?php echo $graph_id; ?>"<?php echo get_dimensions($graph); ?>>
<table class="standard">
<?php foreach ($data as $rowid => $row) {
	echo "<tr>";
	foreach ($row as $i => $item) {
		echo (($i == 0 || $rowid == 0) ? "<th>" : "<td>");
		echo $item;	// assumed to be html escaped
		echo (($i == 0 || $rowid == 0) ? "</th>" : "</td>");
	}
	echo "</tr>\n";
}
?>
</table>
<?php if (isset($graph['extra'])) echo '<div class="graph_extra">' . $graph['extra'] . '</div>'; ?>
</div>
<?php
}

function render_graph_controls($graph) {
?>
<ul class="graph_controls">
	<li class="move_up"><a href="<?php echo htmlspecialchars(url_for('profile', array(
		'page' => $graph['page_id'],
		'move_up' => $graph['id']))); ?>">Move up</a></li>
	<li class="move_down"><a href="<?php echo htmlspecialchars(url_for('profile', array(
		'page' => $graph['page_id'],
		'move_down' => $graph['id']))); ?>">Move down</a></li>
	<li class="remove"><a href="<?php echo htmlspecialchars(url_for('profile', array(
		'page' => $graph['page_id'],
		'remove' => $graph['id']))); ?>">Remove</a></li>
</ul>
<?php
}

function render_graph_last_updated($graph) {
	if (isset($graph['last_updated']) && $graph['last_updated']) { ?>
		<div class="last_updated"><?php echo recent_format_html($graph['last_updated']); ?></div>
	<?php }
}

/**
 * @param $data an associative array of (label => numeric value)
 */
function render_pie_chart($graph, $data, $key_label, $value_label, $callback = 'graph_number_format') {
	$graph_id = htmlspecialchars($graph['id']);
	render_graph_last_updated($graph);
?>
<script type="text/javascript">
  google.load("visualization", "1", {packages:["corechart"]});
  google.setOnLoadCallback(drawChart<?php echo $graph_id; ?>);
  function drawChart<?php echo $graph_id; ?>() {
	var data = google.visualization.arrayToDataTable([
	  ['<?php echo htmlspecialchars($key_label); ?>', '<?php echo htmlspecialchars($value_label); ?>'],
	  <?php
	  foreach ($data as $key => $value) {
		echo "['" . $key . "', " . $callback($value) . "],";
	  } ?>
	]);

	var options = {
//          title: '...'
		legend: {position: 'none'},
		backgroundColor: '#111',
	};

	var chart = new google.visualization.PieChart(document.getElementById('graph_<?php echo $graph_id; ?>'));
	chart.draw(data, options);
  }
</script>

<div id="graph_<?php echo $graph_id; ?>"<?php echo get_dimensions($graph); ?>></div>
<?php if (isset($graph['extra'])) echo '<div class="graph_extra">' . $graph['extra'] . '</div>'; ?>
<?php
}

function render_linegraph_date($graph, $data) {
	$graph_id = htmlspecialchars($graph['id']);
	render_graph_last_updated($graph);
?>
<script type="text/javascript">
  google.load("visualization", "1", {packages:["corechart"]});
  google.setOnLoadCallback(drawChart<?php echo $graph_id; ?>);
  function drawChart<?php echo $graph_id; ?>() {
        var data = new google.visualization.DataTable();
        data.addColumn('date', 'Date');
        <?php for ($i = 1; $i < count($data[0]); $i++) { ?>
        	data.addColumn('number', '<?php echo htmlspecialchars($data[0][$i]); ?>');
        <?php } ?>

        data.addRows([
        <?php for ($i = 1; $i < count($data); $i++) {
        	echo "[" . implode(", ", $data[$i]) . "],\n";
        } ?>
        ]);

        var options = {
			legend: {position: 'none'},
			hAxis: {
				gridlines: { color: '#333' },
				textStyle: { color: 'white' },
				format: 'd-MMM',
			},
			vAxis: {
				gridlines: { color: '#333' },
				textStyle: { color: 'white' },
			},
			backgroundColor: '#111',
        };

	var chart = new google.visualization.LineChart(document.getElementById('graph_<?php echo $graph_id; ?>'));
	chart.draw(data, options);
  }
</script>

<div id="graph_<?php echo $graph_id; ?>"<?php echo get_dimensions($graph); ?>></div>
<?php if (isset($graph['extra'])) echo '<div class="graph_extra">' . $graph['extra'] . '</div>'; ?>
<?php
}

function get_dimensions($graph) {
	return ' style="width: ' . (get_site_config('default_graph_width') * $graph['width']) . 'px; height: ' . (get_site_config('default_graph_height') * $graph['height']) . 'px;"';
}

// cached
$global_all_summary_instances = null;
function get_all_summary_instances() {
	global $global_all_summary_instances;
	if ($global_all_summary_instances === null) {
		$global_all_summary_instances = array();
		$q = db()->prepare("SELECT * FROM summary_instances WHERE user_id=? AND is_recent=1");
		$q->execute(array(user_id()));
		while ($summary = $q->fetch()) {
			$global_all_summary_instances[$summary['summary_type']] = $summary;
		}
	}
	return $global_all_summary_instances;
}

// cached
$global_all_summaries = null;
function get_all_summaries() {
	global $global_all_summaries;
	if ($global_all_summaries === null) {
		$global_all_summaries = array();
		$q = db()->prepare("SELECT * FROM summaries WHERE user_id=?");
		$q->execute(array(user_id()));
		while ($summary = $q->fetch()) {
			$global_all_summaries[$summary['summary_type']] = $summary;
		}
	}
	return $global_all_summaries;
}

// cached
$global_all_offset_instances = null;
function get_all_offset_instances() {
	global $global_all_offset_instances;
	if ($global_all_offset_instances === null) {
		$global_all_offset_instances = array();
		$q = db()->prepare("SELECT * FROM offsets WHERE user_id=? AND is_recent=1");
		$q->execute(array(user_id()));
		while ($offset = $q->fetch()) {
			$global_all_offset_instances[$offset['currency']] = $offset;
		}
	}
	return $global_all_offset_instances;
}

function get_all_summary_currencies() {
	$summaries = get_all_summaries();
	$result = array();
	foreach ($summaries as $s) {
		// assumes all summaries start with 'summary_CUR_optional'
		$c = substr($s['summary_type'], strlen("summary_"), 3);
		$result[$c] = $s['summary_type'];
	}
	return $result;
}

function get_all_conversion_currencies() {
	$summaries = get_all_summaries();
	$result = array();
	foreach ($summaries as $s) {
		// assumes all summaries start with 'summary_CUR_optional'
		$c = substr($s['summary_type'], strlen("summary_"), 3);
		$result[$s['summary_type']] = $c;
	}
	return $result;
}

// cached
$global_all_recent_rates = null;
// this also makes assumptions about which is the best exchange for each rate
// e.g. btc-e for btc/ltc, mtgox for usd/btc
function get_all_recent_rates() {
	global $global_all_recent_rates;
	if ($global_all_recent_rates === null) {
		$global_all_recent_rates = array();
		$q = db()->prepare("SELECT * FROM ticker WHERE is_recent=1 AND (
			(currency1 = 'btc' AND currency2 = 'ltc' AND exchange='btce') OR
			(currency1 = 'btc' AND currency2 = 'nmc' AND exchange='btce') OR
			(currency1 = 'nzd' AND currency2 = 'btc' AND exchange='bitnz') OR
			(currency1 = 'usd' AND currency2 = 'btc' AND exchange='mtgox') OR
			0
		)");
		$q->execute(array(user_id()));
		while ($ticker = $q->fetch()) {
			$global_all_recent_rates[$ticker['currency1'] . $ticker['currency2']] = $ticker;
		}
	}
	return $global_all_recent_rates;
}

class GraphException extends Exception { }