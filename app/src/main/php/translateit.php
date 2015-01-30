<?php
header('Content-Type:text/html; charset=UTF-8');

function translate($translate_to, $matching_ids, $dbc){

    	// Represents the id name in the table so for Russian it
    	// would be russian_id
    	$translate_id = $translate_to . '_id';

/* OLD QUERY THAT DELETES DUPLICATES

SELECT word FROM russian WHERE russian_id in (131,26,69)
ORDER BY CASE russian_id
WHEN 131 THEN 1
WHEN 26 THEN 2
WHEN 69 THEN 3
WHEN 26 THEN 4
END;

NEW TRANSLATE QUERY
SELECT russian.word
FROM russian
JOIN(
SELECT 131 AS id, 1 AS word_order
UNION ALL SELECT 26, 2
UNION ALL SELECT 26, 3
UNION ALL SELECT 69, 4) WordsToSearch ON russian.russian_id = WordsToSearch.id
ORDER BY WordsToSearch.word_order;
*/

		$translate_query = 'SELECT word FROM ' . $translate_to . ' JOIN(' .
			'SELECT ' . $matching_ids[0] . ' AS id, 1 AS word_order';

		$array_size_2 = count($matching_ids);

		for ($i = 1; $i < $array_size_2; $i++) {
    		$translate_query = $translate_query . " UNION ALL SELECT '" . $matching_ids[$i] .
    		"', " . ($i + 1) . " ";
		}

		$translate_query = $translate_query . ') WordsToSearch ON ' .
			$translate_to . '.' . $translate_id . ' = WordsToSearch.id
			ORDER BY WordsToSearch.word_order;';

		// END OF NEW TRANSLATE QUERY

    	// Issue the query to the database
    	$translate_response = mysqli_query($dbc, $translate_query);

    	if($translate_response){

        		while($row = mysqli_fetch_array($translate_response)){

        				$translated_text = $translated_text . ' ' . $row['word'];

        		}

    	}

    	return $translated_text;

} // Close function translate

function get_translation($translate_to, $english_words){

		// Trim white space from the name and store the name
        $english_words = trim($english_words);

        $english_array = array();

        // Break the words into an array
        $english_array = explode(" ", $english_words);

        // Get a connection to the database
		include('../mysqli_connect_language.php');

		// Set character set in PHP to get proper characters
		$dbc->set_charset("utf8");

/*
OLD QUERY THAT DELETES DUPLICATES

select english_id, word from english where word in ("Here", "are", "are", "dogs")
order by CASE word
WHEN "here" then 1
WHEN "are" then 2
WHEN "are" then 3
WHEN "dogs" then 4
END;

BEGINNING OF THE NEW QUERY -------------

UNION ALL combines the result set of multiple SELECT statements
which makes sure we receive all rows even if there are
duplicates

SELECT english.word
FROM english
JOIN (
SELECT 'A' AS word, 1 AS word_order
UNION ALL SELECT 'dog', 2
UNION ALL SELECT 'a', 3
UNION ALL SELECT 'cat', 4) WordsToSearch ON english.word = WordsToSearch.word
ORDER BY WordsToSearch.word_order;
*/

		$query = "SELECT english_id, english.word FROM english JOIN(
		SELECT '" . $english_array[0] . "' AS word, 1 AS word_order";

		// Get the size of the array
		$array_size = count($english_array);

		for ($i = 1; $i < $array_size; $i++) {
    		$query = $query . " UNION ALL SELECT '" . $english_array[$i] .
    		"', " . ($i + 1) . " ";
		}

		$query = $query . ") WordsToSearch ON english.word = WordsToSearch.word
ORDER BY WordsToSearch.word_order;";

		// END OF THE NEW QUERY -------------------

        // Issue the query to the database
        $response = @mysqli_query($dbc, $query);

        // Array that contains the matching ids in order
        $matching_ids = array();

        if($response){

        	while($row = mysqli_fetch_array($response)){

        		$matching_ids[] = $row['english_id'];

        		// Holds the array after the select
        		$array_after_query[] = $row['word'];

        	}

        } // Close if($response)

        return translate($translate_to, $matching_ids, $dbc);

}

function get_all_translations($english_words){

	$language_array = array("arabic", "chinese", "danish", "dutch",
							"french", "german", "italian", "portuguese",
							"russian", "spanish");

	$translations_array = array();

	foreach($language_array as $language){

		$translations_array[] = get_translation($language,
												$english_words);

	}

	$all_translations = '{"translations": [';

	$index = 0;

	foreach($language_array as $language){

		$all_translations = $all_translations . '{"' . $language .
			'":"' . $translations_array[$index] . '"},';

		$index++;

	}

	$all_translations = rtrim($all_translations, ",");

	$all_translations = $all_translations . ']}';

	return $all_translations;

}

// ---------------------- NEW STUFF ----------------------
function get_xml_translations($english_words){

	// Used as the tag names that will surround each translation
	$language_array = array("arabic", "chinese", "danish", "dutch",
							"french", "german", "italian", "portuguese",
							"russian", "spanish");

	// Will hold all translations
	$translations_array = array();

	foreach($language_array as $language){

		$translations_array[] = get_translation($language,
												$english_words);

	}

	// Define that all data will be between 2 translations tags
	$xml = new SimpleXMLElement("<translations />");

	// Index for $language_array
	$lang_index = 0;

	// Take each translation and put it between the correct
	// language tag
	foreach($translations_array as $translation){

		$xml->addChild($language_array[$lang_index], $translation);

		$lang_index++;

	}

	// Generate a DOM document we can style
	// A DOM Document represents an entire XML document
	$dom = dom_import_simplexml($xml)->ownerDocument;

	// State we want the data to be indented
	$dom->formatOutput = true;

	// saveXML converts the XML into a string to print
	return $dom->saveXML();

}
// ---------------------- NEW STUFF ----------------------

if(isset($_GET["action"])){

	switch($_GET["action"]){

		case "translate":
			$translate_to = $_GET["language"];
			$english_words = urldecode($_GET["english_words"]);
			$translate_text = get_translation($translate_to,
											  $english_words);
			break;

		case "translations":
			$english_words = urldecode($_GET["english_words"]);
			$translated_text = get_all_translations($english_words);
			header('Content-Type:application/json; charset=UTF-8');
			break;

		// ---------------------- NEW STUFF ----------------------
		case "xmltranslations":
			$english_words = urldecode($_GET["english_words"]);
			$translated_text = get_xml_translations($english_words);
			header('Content-Type:text/xml; charset=UTF-8');
			break;

		// ---------------------- NEW STUFF ----------------------

	}

}

echo $translated_text;

?>