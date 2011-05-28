<?php
	$cli_mode = true;
	require('header.php');

	// XXX lost dingen recoveren

	$albums = array();
	$photos = array();
	scan_gallery('');

	$res = mysql_query('SELECT name, path FROM fa_albums');
	while($row = mysql_fetch_assoc($res)) {
		if(!isset($albums[$row['path']]) || !in_array($row['name'], $albums[$row['path']])) {
			mysql_query("UPDATE fa_albums SET visibility='lost' WHERE name='". addslashes($row['name']) ."' AND path='". addslashes($row['path']) ."'");
		} else {
			unset($albums[$row['path']][array_search($row['name'], $albums[$row['path']])]);
		}
	}

	foreach($albums as $path=>$dirs) {
		foreach($dirs as $album) {
			if(isset($photos[$path . $album .'/']) || isset($albums[$path . $album .'/'])) {
				mysql_query("INSERT INTO fa_albums (name, path) VALUES ('". addslashes($album) ."', '". addslashes($path) ."')");
			}
		}
	}

	$res = mysql_query('SELECT name, path FROM fa_photos');
	while($row = mysql_fetch_assoc($res)) {
		if(!isset($photos[$row['path']]) || !in_array($row['name'], $photos[$row['path']])) {
			mysql_query("UPDATE fa_photos SET visibility='lost' WHERE name='". addslashes($row['name']) ."' AND path='". addslashes($row['path']) ."'");
		} else {
			unset($photos[$row['path']][array_search($row['name'], $photos[$row['path']])]);
		}
	}

        // Insert the remaining images
	foreach($photos as $path=>$dirs) {
		foreach($dirs as $photo) {
                        // Extract EXIF data to determine rotation
                        $exif = exif_read_data($fotodir.$path.$photo, 'IFD0');
                        $raw_or = isset($exif['Orientation']) ?
                                        $exif['Orientation'] : 0;
                        if($raw_or == 1 or $raw_or == 0)
                                $or = 0;
                        elseif($raw_or == 3)
                                $or = 180;
                        elseif($raw_or == 6)
                                $or = 90;
                        elseif($raw_or == 8)
                                $or = 270;
                        else {
                                echo "\n";
                                echo "Unknown orientation: " . $raw_or . "\n";
                                echo "  (".$path.$photo."\n";
                                $or = 0;
                        }

                        // Insert image into database
                        mysql_query("INSERT INTO fa_photos (
                                        name,
                                        path,
                                        rotation)
                                VALUES ('". addslashes($photo) ."',
                                        '". addslashes($path) ."',
                                        '". $or."')");
		}
	}

	function scan_gallery($path) {
		global $fotodir, $albums, $photos, $extensions;

		foreach(scandir($fotodir . $path) as $f) {
			if($f[0] == '.') {
				continue;
			}
			if(true || is_readable($fotodir . $path . $f)) {
				if(is_dir($fotodir . $path . $f)) {
					$albums[$path][] = $f;
					scan_gallery($path . $f .'/');
				} elseif(isset($extensions[getext($f)]) && $path != '') {
					$photos[$path][] = $f;
				}
			}
		}
	}
?>
