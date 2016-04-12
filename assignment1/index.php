<?php

// Load database connection, helpers, etc.
require_once(__DIR__ . '/errors.php');
require_once(__DIR__ . '/include.php');

$period = 12; // default Life-Time of 12 months
$commission = 0.10; // default 10% commission

// basic checks of POST data, will just use defaut values on unexpected input 
if (isset($_POST['period_length']) && gettype($_POST['period_length']) === "integer")
	$period = (integer)$_POST['period_length'];

if (isset($_POST['commission']) && gettype($_POST['commission']) === "integer")
	$commission = (float)$_POST['commission'] / 100;

$period_seconds = $period * 30 * 24 * 60 * 60;

// get a line per booker with his first booking date, the amount of booking done and the total paid
// during the given period
// I was not sure about the last phrase of the Readme.md so I just filtered out all products 
$result = $db->prepare('
SELECT b_timestamp.first_booking_timestamp AS first_booking_timestamp,
		   count(bookings.id) AS total_bookings, 
		   sum(bookingitems.locked_total_price) AS total_price
FROM bookingitems
JOIN bookings ON bookings.id = bookingitems.booking_id
JOIN (
	SELECT booker_id, min(end_timestamp) as first_booking_timestamp FROM bookings 
	JOIN bookingitems ON bookings.id = bookingitems.booking_id 
	JOIN spaces ON bookingitems.item_id = spaces.item_id
	GROUP BY booker_id ) as b_timestamp
ON bookings.booker_id = b_timestamp.booker_id
WHERE bookingitems.end_timestamp <= (b_timestamp.first_booking_timestamp + ' . (string)$period_seconds . ')
GROUP BY bookings.booker_id
')->run()->fetchAll();


function updateReport($LTVReport, $bookerReport) {
	$monthKey = DateTime::createFromFormat('U', $bookerReport->first_booking_timestamp)->format('Y-m');

	// create the month line if it does not exists in the report
	if (!array_key_exists($monthKey, $LTVReport))
		$LTVReport[$monthKey] = array(
			'bookerNumber' => 0,
			'totalBookings' => 0,
			'totalPrice' => 0
		);

	$LTVReport[$monthKey]['bookerNumber']++;
	$LTVReport[$monthKey]['totalBookings'] += $bookerReport->total_bookings;
	$LTVReport[$monthKey]['totalPrice'] += $bookerReport->total_price;

	return $LTVReport;
}


$LTVReport = array();

// for each booker we update the corresponding month line
foreach ($result as $bookerReport) {
	$LTVReport = updateReport($LTVReport, $bookerReport);
}


function generateSelectOptions($currentPeriod, $maxPeriodLength) {
	for ($i = 1; $i <= $maxPeriodLength; $i++) { ?>
		<option value="<?php echo $i ?>" <?php echo ($currentPeriod === $i ? 'selected': '') ?>><?php echo $i ?></option>
	<?php
	}
}

?>

<!doctype html>
<html>
	<head>
		<title>Assignment 1: Create a Report (SQL)</title>
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<style type="text/css">
			.report-table
			{
				width: 100%;
				border: 1px solid #000000;
			}
			.report-table td,
			.report-table th
			{
				text-align: left;
				border: 1px solid #000000;
				padding: 5px;
			}
			.report-table .right
			{
				text-align: right;
			}
		</style>
	</head>
	<body>
		<form method="POST">
			Period of
			<select name="period_length">
				<?php generateSelectOptions($period, 24) ?>
			</select>
			months, with a 
			<input type="number" step="1" name="commission" min="0" max="100" value="<?php echo $commission * 100 ?>">
			% commission
			<input type="submit" value="Generate report">
		</form>

		<h1>Report:</h1>
		<table class="report-table">
			<thead>
				<tr>
					<th>Start</th>
					<th>Bookers</th>
					<th># of bookings (avg)</th>
					<th>Turnover (avg)</th>
					<th>LTV</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($LTVReport as $month => $reportLine): 
					$bookerNumber = $reportLine['bookerNumber'];
					$totalBookings = $reportLine['totalBookings'];
					$turnover = $reportLine['totalPrice'] / $bookerNumber;
					$LTV = $turnover * $commission;
				?>
					<tr>
						<td><?= DateTime::createFromFormat('Y-m', $month)->format('F Y') ?></td>
						<td><?= $bookerNumber ?></td>
						<td><?= round($totalBookings / $bookerNumber, 1) ?></td>
						<td><?= round($turnover, 2) ?></td>
						<td><?= round($LTV, 2) ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="4" class="right"><strong>Total rows:</strong></td>
					<td><?= count($LTVReport) ?></td>
				</tr>
			</tfoot>
		</table> 
	</body>
</html>