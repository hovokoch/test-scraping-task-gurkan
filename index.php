<?php
$scrapeUrl = runCURL('https://www.buyatoyota.com/greaterny/offers/?limit=999999&filters=lease');

// get all .offer-card elements
$dom = new DOMDocument();
@$dom->loadHTML($scrapeUrl);
$xpath = new DOMXPath($dom);
$elements = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' offer-card ')]");

$csvData = [];
foreach ($elements as $element) {
    $link = $element->getElementsByTagName('a')->item(0)->getAttribute('href');
    $vehicleURL = 'https://www.buyatoyota.com' . $link;

    $vehicleOutput = runCURL($vehicleURL);
    $vehicleData = processVehicleData($vehicleOutput);

    $csvData[] = $vehicleData;
}


// Open the CSV file for writing
$fp = fopen('scrape-data.csv', 'w');

// Write the header row
$header = [
    'year',
    'make',
    'model',
    'trim',
    'msrp',
    'monthly_payment',
    'monthly_payment_zero',
    'term',
    'due_at_signing',
    'annual_miles',
    'acquisition_fee',
    'residual_value',
    'residual_perc',
    'capitalized_cost',
    'money_factor',
    'interest_rate',
    'mileage_overage',
    'disposition_fee',
    'end_date'
];
fputcsv($fp, $header);

// Process each row of data
foreach ($csvData as $fields) {

    $values = [
        $fields['year'],
        $fields['make'],
        $fields['model'],
        $fields['trim'],
        $fields['msrp'],
        $fields['monthly_payment'],
        $fields['monthly_payment_zero'],
        $fields['term'],
        $fields['due_at_signing'],
        $fields['annual_miles'],
        $fields['acquisition_fee'],
        $fields['residual_value'],
        $fields['residual_perc'],
        $fields['capitalized_cost'],
        $fields['money_factor'],
        $fields['interest_rate'],
        $fields['mileage_overage'],
        $fields['disposition_fee'],
        $fields['end_date'],
    ];

    // Write to CSV
    fputcsv($fp, $values);
}

// Close the CSV file
fclose($fp);

var_dump('CSV file created successfully');

function processVehicleData($vahicleObject)
{
    $vehicleData = [];

    $vehicleData['make'] = 'Toyota';

    // create dom object to parse vehicle data
    $dom = new DOMDocument();
    @$dom->loadHTML($vahicleObject);
    $xpath = new DOMXPath($dom);

    // get vehicle title
    $vehicleTitle = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' fs67XEFk ')]")->item(0)->nodeValue;

    // get year from title
    $vehicleData['year'] = substr($vehicleTitle, 0, 4);

    // get model from title
    $vehicleData['model'] = preg_replace('/\d{4} /', '', substr($vehicleTitle, 5, -12));

    // get monthly_payment
    $monthly_payment = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' Oh8RSPMo ')]")->item(0)->nodeValue;
    $vehicleData['monthly_payment'] = str_replace('$', '', $monthly_payment);

    // get term
    $vehicleData['term'] = $xpath->query('//div[@class="nwRgtff_ offer-dt-number"]')->item(0)->nodeValue;

    // get due_at_signing
    $due_at_signing = $xpath->query('//div[@class="nwRgtff_ offer-dt-number"]')->item(1)->nodeValue;
    // remove , and $
    $vehicleData['due_at_signing'] = str_replace([',', '$'], '', $due_at_signing);

    // get description
    $description = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' sRuZXorF ')]")->item(0)->nodeValue;

    $vehicleData['trim'] = getRegex('trim', $description, $vehicleData['year'] . " " . $vehicleData['model']);
    $vehicleData['acquisition_fee'] = getRegex('acquisition_fee', $description);

    // get disclaimerContent
    $disclaimer = $xpath->query("//*[contains(concat(' ', normalize-space(@id), ' '), ' disclaimerContent ')]/li")->item(0)->nodeValue;

    $vehicleData['msrp'] = str_replace(',', '', getRegex('msrp', $disclaimer));
    $vehicleData['capitalized_cost'] = str_replace(',', '', getRegex('capitalized_cost', $disclaimer));
    $vehicleData['residual_value'] = str_replace(',', '', getRegex('residual_value', $disclaimer));
    $vehicleData['mileage_overage'] = getRegex('mileage_overage', $disclaimer);
    $vehicleData['annual_miles'] = str_replace(',', '', getRegex('annual_miles', $disclaimer));
    $vehicleData['disposition_fee'] = getRegex('disposition_fee', $disclaimer);
    $vehicleData['end_date'] = getRegex('end_date', $disclaimer);

    // staring calculation data

    $monthly_payment_zero = $vehicleData['monthly_payment'] + (($vehicleData['due_at_signing'] - $vehicleData['monthly_payment']) / $vehicleData['term']);
    $vehicleData['monthly_payment_zero'] = round($monthly_payment_zero, 2);

    $residual_perc = ($vehicleData['residual_value'] / $vehicleData['msrp']) * 100;
    $vehicleData['residual_perc'] = round($residual_perc);

    $money_factor = ($vehicleData['monthly_payment'] - (($vehicleData['capitalized_cost'] - $vehicleData['residual_value']) / $vehicleData['term'])) / ($vehicleData['capitalized_cost'] + $vehicleData['residual_value']);
    $vehicleData['money_factor'] = round($money_factor, 8);

    $interest_rate = $vehicleData['money_factor'] * 2400;
    $vehicleData['interest_rate'] = round($interest_rate, 1);

    return $vehicleData;
}

function runCURL($url)
{
    $userAgent = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function getRegex($mode, $string, $search = null)
{
    switch ($mode) {
        case 'trim':
            preg_match('/' . preg_quote($search, '/') . '\s+(.*?)\s+Model/', $string, $matches);
            break;
        case 'acquisition_fee':
            preg_match('/acquisition fee of\s*\$(\d+(?:,\d{3})*(?:\.\d{2})?)/', $string, $matches);
            break;
        case 'msrp':
            preg_match('/Total SRP of\s*\$(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/', $string, $matches);
            break;
        case 'capitalized_cost':
            preg_match('/net capitalized cost of\s*\$(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/', $string, $matches);
            break;
        case 'residual_value':
            preg_match('/purchase amount of\s*\$(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/', $string, $matches);
            break;
        case 'mileage_overage':
            preg_match('/will pay\s*\$(\d+\.\d{2}) per mile/', $string, $matches);
            break;
        case 'annual_miles':
            preg_match('/per mile for all mileage over\s*(\d{1,3}(?:,\d{3})*)\s*miles per year/', $string, $matches);
            break;
        case 'disposition_fee':
            preg_match('/\$(\d+)\s*disposition fee is due at lease end/', $string, $matches);
            break;
        case 'end_date':
            preg_match('/Expires (\d{2}-\d{2}-\d{4})\./', $string, $matches);
            break;
    }

    if (isset($matches[1])) {
        return trim($matches[1]);
    } else {
        return '';
    }
}
?>