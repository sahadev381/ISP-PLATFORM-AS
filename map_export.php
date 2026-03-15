<?php
if (ob_get_length()) ob_end_clean();

include 'config.php';
include 'includes/auth.php';

// Fetch data
$nodes = $conn->query("SELECT * FROM ftth_nodes");
$routes = $conn->query("SELECT * FROM fiber_routes");
$customers = $conn->query("SELECT username, full_name, lat, lng FROM customers WHERE lat IS NOT NULL");

// Set headers for KML download
header('Content-Type: application/vnd.google-earth.kml+xml');
header('Content-Disposition: attachment; filename="ftth_network_export_' . date('Y-m-d') . '.kml"');

// Initialize KML structure
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
<name>FTTH Network Infrastructure</name>
<description>Exported from ISP Management System</description>

<Style id="oltStyle"><IconStyle><color>ff0000ff</color><scale>1.2</scale><Icon><href>http://maps.google.com/mapfiles/kml/paddle/red-circle.png</href></Icon></IconStyle></Style>
<Style id="poleStyle"><IconStyle><color>ff808080</color><scale>0.8</scale><Icon><href>http://maps.google.com/mapfiles/kml/paddle/wht-blank.png</href></Icon></IconStyle></Style>
<Style id="dbStyle"><IconStyle><color>ffff0000</color><scale>1.0</scale><Icon><href>http://maps.google.com/mapfiles/kml/paddle/blu-circle.png</href></Icon></IconStyle></Style>
<Style id="fiberStyle"><LineStyle><color>ffff0000</color><width>4</width></LineStyle></Style>

<Folder>
    <name>Infrastructure Nodes</name>
    <?php while($n = $nodes->fetch_assoc()): 
        $style = 'dbStyle';
        if($n['type'] == 'OLT') $style = 'oltStyle';
        if($n['type'] == 'POLE') $style = 'poleStyle';
    ?>
    <Placemark>
        <name><?= htmlspecialchars($n['type'] . ': ' . $n['name']) ?></name>
        <description>Capacity: <?= $n['capacity'] ?></description>
        <styleUrl>#<?= $style ?></styleUrl>
        <Point><coordinates><?= $n['lng'] ?>,<?= $n['lat'] ?>,0</coordinates></Point>
    </Placemark>
    <?php endwhile; ?>
</Folder>

<Folder>
    <name>Fiber Routes</name>
    <?php while($r = $routes->fetch_assoc()): 
        $path_data = json_decode($r['path_data'], true);
        if(is_array($path_data)):
            $coords = [];
            foreach($path_data as $p) { $coords[] = $p['lng'] . ',' . $p['lat'] . ',0'; }
    ?>
    <Placemark>
        <name><?= htmlspecialchars($r['name']) ?></name>
        <styleUrl>#fiberStyle</styleUrl>
        <LineString>
            <tessellate>1</tessellate>
            <coordinates><?= implode(' ', $coords) ?></coordinates>
        </LineString>
    </Placemark>
    <?php endif; endwhile; ?>
</Folder>

<Folder>
    <name>Customers</name>
    <?php while($c = $customers->fetch_assoc()): ?>
    <Placemark>
        <name><?= htmlspecialchars($c['full_name']) ?></name>
        <Point><coordinates><?= $c['lng'] ?>,<?= $c['lat'] ?>,0</coordinates></Point>
    </Placemark>
    <?php endwhile; ?>
</Folder>

</Document>
</kml>
