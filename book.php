<?
	include("textfunctions.php");

//	$text = file_get_contents('2701-0.txt');
	$text = file_get_contents('https://www.gutenberg.org/files/2701/2701-0.txt');
	
	// remove Gutenberg header/footer and other preamble
	$text = substr($text, 29626);
	$text = preg_replace("/\*\*\* END OF THE PROJECT GUTENBERG EBOOK.+$/s","",$text);
	
	$text = trim($text);
	$text = preg_replace("/\r\n/","\n",$text);
	$text = preg_replace("/CHAPTER \d+\. [^\n]+\n?[^\n]*\n\n/s","@CHAPTER@",$text);
	$text = preg_replace("/\nEpilogue\n/","@CHAPTER@",$text);
	$text = preg_replace("/\n\n/","@PARA@",$text);
	$text = preg_replace("/[\n\r]+/"," ",$text);
	$text = preg_replace("/([?!;])/","$1.",$text);

	$sentences = explode('.', $text);
	
	
	$entries = array();
	$hooks = array();
	$nonverbs = array();
	$currentEntry = "";
	$paracount = 0;
	
	foreach ($sentences as $sentence)
	{
		$sentence = cleanQuotes($sentence).".";
		
		if (preg_match("/@CHAPTER@/",$sentence)) // chapter break, always break it
		{
			$entries = queueEntry($entries,$currentEntry,$hooks);
			$currentEntry = "";
			$hooks = array();

		}
		elseif (preg_match("/@PARA@/",$sentence)) // paragraph break, could break the story here
		{
			if (sizeof($hooks)>0 && strlen($currentEntry)>800)
			{
				$entries = queueEntry($entries,$currentEntry,$hooks);
				$currentEntry = "";
				$hooks = array();
			}
		}
		
		$sentence = preg_replace("/@(CHAPTER|PARA)@/","\n\n",$sentence);
		
		$currentEntry .= "$sentence";		
		
		// there's a lot of "you would have" and "let us" in Moby-Dick, which will do as "I..." statements
		$sentence = preg_replace("/(you would have)/i","I",$sentence);
		$sentence = preg_replace("/(let us|let us now|let's|all men)/i","we",$sentence);
		$sentence = preg_replace("/\b(ye|thou)\b/i","you",$sentence);

		// other synonyms
		$sentence = preg_replace("/(I|we|you)'ve/i","$1 have",$sentence);
		$sentence = preg_replace("/(I|we|you)'ll/i","$1 will",$sentence);
		$sentence = preg_replace("/(I|we|you)'d/i","$1 would",$sentence);
		
		// strip out skippable words
	    $sentence = preg_replace("/(I|we|you) (would have|would almost|can'st) /i","$1 ",$sentence);
	    $sentence = preg_replace("/(I|we|you) (can|will|may|then|first|always|could|should|would|must|now|at last|had|have|shall|hast|but|ever|[^ ]+ly) /i","$1 ",$sentence);
		// pre-modify verb starts which are hard to work with (but which are fine in later parts of sentences)
		$sentence = preg_replace("/(I|we) (did|have|won't)/i","you $1",$sentence);
				
		$foundhook = 0;
		
		// first pass for good-length sentences, so that ones with a late I don't get picked instead
		
		if (preg_match("/\b(I) ([a-z]+)\b([^\.?!\"\”:;\(\)]{30,110})[\.?!:;,\(\)\"]/",$sentence,$matches))
		{
			$present = presentVerb($matches[2]);
			if ($present != "")
			{
				$hook = "To ".frameChoice($present.$matches[3]).", turn to page ".(sizeof($entries)).".";
				array_push($hooks,$hook);				
				$foundhook = 1;
			}
			else
			{
				array_push($nonverbs,$matches[2]);				
			}
		}
		
		// now a pass for longer and shorter I-sentences
		if ($foundhook == 0 && preg_match("/\b(I) ([a-z]+)\b([^\.?!\"\”:;\(\)]{5,170})[\.?!:;,\(\)\"]/",$sentence,$matches))
		{
			$present = presentVerb($matches[2]);
			if ($present != "")
			{
				$hook = "To ".frameChoice($present.$matches[3]).", turn to page ".(sizeof($entries)).".";
				array_push($hooks,$hook);				
				$foundhook = 1;
			}
			else
			{
				array_push($nonverbs,$matches[2]);				
			}
		}
		
		// settle for a good length "we" statement as third best, if needed
		if ($foundhook == 0 && preg_match("/\b(we) ([a-z]+)\b([^\.?!\"\”:;\(\)]{30,110})[\.?!:;,\(\)\"]/i",$sentence,$matches))
		{
			$present = presentVerb($matches[2]);
			if ($present != "")
			{
				$hook = "To ".frameChoice($present.$matches[3]).", turn to page ".(sizeof($entries)).".";
				array_push($hooks,$hook);				
				$foundhook = 1;
			}
			else
			{
				array_push($nonverbs,$matches[2]);				
			}
		}
		
		// and any sane length "we/you" as fourth best
		if ($foundhook == 0 && preg_match("/\b(you|we) ([a-z]+)\b([^\.?!\"\”:;\(\)]{5,170})[\.?!:;,\(\)\"]/i",$sentence,$matches))
		{
			$present = presentVerb($matches[2]);
			if ($present != "")
			{
				$hook = "To ".frameChoice($present.$matches[3]).", turn to page ".(sizeof($entries)).".";
				array_push($hooks,$hook);				
			}
			else
			{
				array_push($nonverbs,$matches[2]);				
			}
		}
		
	}
	
	array_push($entries,array("order"=>sizeof($entries),"text"=>$currentEntry,"hooks"=>$hooks));
	unset($entries[0]);
	
	//print_r($entries);
	
	// shuffle pages
	for ($i=0; $i<sizeof($entries); $i++)
	{
		$first = rand(2,sizeof($entries)-1);
		$second = min($first+rand(1,10),sizeof($entries)-1);
		
		$swap = $entries[$second];
		$entries[$second] = $entries[$first];
		$entries[$first] = $swap;
	}
	
	/*
		print_r($entries);
		sort($nonverbs);
		print_r($nonverbs);
		exit;
	*/
	
	// start the output
	
	?>
	<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "https://www.w3.org/TR/html4/strict.dtd"><html><head><title>Moby-Dick Choose-Your-Own-Adventure</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style>
@import url('https://fonts.googleapis.com/css2?family=PT+Serif&family=IM+Fell+English+SC&display=swap');
body { margin:64px 25%; font-family: PT Serif,serif; }
h1 { text-align:center; padding: 0px 0px 0px 0px; font-family:IM Fell English SC, serif; }
h2 { text-align:center; padding: 32px 0px 0px 0px; font-family:IM Fell English SC, serif; }
.sub { padding:0px; font-style:italic }

.intro { font-size:0.9em; color:#777 }
.intro a { color:#779; }

@media screen and (max-width: 700px) {

body { margin:5%; }
ul { padding: 0px 5%; }
}
</style>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<h1>Some Dim, Random Way</h1>
<h2 class="sub">A choose-your-own-adventure Moby-Dick</h2>

<p class="intro">This book was generated automatically from <a href="https://www.gutenberg.org/files/2701/2701-0.txt">the text of Herman Melville's <i>Moby-Dick</i></a>, processed
by <a href="https://github.com/kevandotorg/nanogenmo-2021">a script</a> written by <a href="https://kevan.org">Kevan Davis</a>.
<i>Some Dim, Random Way</i> was created for <a href="https://github.com/NaNoGenMo/2021">NaNoGenMo 2021</a>.</p>

	<?

	$hooksUsedAlready = array();

	for ($i=1; $i<sizeof($entries)+1; $i++)
	{
		print "<h2 id=\"p".$i."\"> ".($i)."</h2>";
		
		$pageText = $entries[$i]["text"];

		// format punctuation, strip unwanted fragments
		$pageText = preg_replace("/\.\./",".",$pageText);
		$pageText = preg_replace("/\n\n/","</p><p>",$pageText);
		$pageText = preg_replace("/\(_([^_]*)_\)/","<i>($1)</i>",$pageText);
		$pageText = preg_replace("/_([^_]*)_/","<i>$1</i>",$pageText);
		$pageText = preg_replace("/--/","&mdash;",$pageText);

		$pageText = "<p>".$pageText."</p>\n\n";
		$pageText = preg_replace("/<p>\*<\/p>/","",$pageText);
		$pageText = preg_replace("/([!?;])\./","$1",$pageText);
		
		print $pageText;

		print "<ul>";
		if ($i==sizeof($entries))
		{
			print "<li>Your story ends here.</li>";
		}
		else
		{
			$links = rand(2,4);
			if (rand(1,10)==1) { $links = 5; }
			if ($i==1) { $links = rand(3,4); }
			$linksMade = 0;
			$boredom = 0;
			$doneLinks = array();
			$hookStrings = array();
			$noAdjacentHook = 0;
			$nextCanonPage = 0;
			
			// find the next page of the original book and add it as an option
			for ($j=1; $j<sizeof($entries); $j++)
			{
				if ($entries[$j]["order"] == $entries[$i]["order"]+1)
				{
					$nextCanonPage = $j;
					$hooks = $entries[$j]["hooks"];
					if (sizeof($hooks)>0)
					{
						array_push($doneLinks,$j);
						
						shuffle($hooks);
						$chosenHook = $hooks[0];
						foreach($hooks as $possibleHook)
						{
							if (!in_array($possibleHook,$hooksUsedAlready)) // try to make sure that every hook gets used at least once
							{
								$chosenHook = $possibleHook;
							}
						}
						
						if (!in_array($chosenHook,$hooksUsedAlready)) { array_push($hooksUsedAlready,$chosenHook); }
						
						$hook = preg_replace("/turn to page \d+\./","<a href=\"#p".$j."\">turn to page ".$j."</a>.",$chosenHook);
						array_push($hookStrings,"$hook");
						$linksMade++;
					}
					else { $noAdjacentHook = 1; }
				}
			}
						
			// add some more options
			while ($linksMade < $links && $boredom < 1000)
			{
				$jumpto = rand(1,min($i+30,sizeof($entries)));
				
				$hooks = $entries[$jumpto]["hooks"];
							
				if (sizeof($hooks)>0 && ($entries[$jumpto]["order"] > $entries[$i]["order"]) && ($entries[$jumpto]["order"] < $entries[$i]["order"]+$linksMade*10+2+$boredom))
				{
					if (!in_array($jumpto, $doneLinks))
					{
						array_push($doneLinks,$jumpto);
						
						shuffle($hooks);
						$chosenHook = $hooks[0];
						foreach($hooks as $possibleHook)
						{
							if (!in_array($possibleHook,$hooksUsedAlready))
							{
								
								$chosenHook = $possibleHook;
							}
						}
						
						if (!in_array($chosenHook,$hooksUsedAlready)) { array_push($hooksUsedAlready,$chosenHook); }
						
						$hook = preg_replace("/turn to page \d+\./","<a href=\"#p".$jumpto."\">turn to page ".$jumpto."</a>.",$chosenHook);
						array_push($hookStrings,"$hook");
						$linksMade++;
					}
				}
				else if (rand(1,50)==1)
				{ $boredom += 1; }
			}
			
			if ($linksMade == 0) // emergency crash response in case we didn't find any links at all somehow
			{
				print "<li> Your story ends here.";
			}
			else
			{
				shuffle($hookStrings);
				foreach ($hookStrings as $hookString)
				{
					$hookString = preg_replace("/_([^_]*)_/","<i>$1</i>",$hookString);
					$hookString = preg_replace("/--/","&mdash;",$hookString);
					print "<li>$hookString</li>";
				}
				if ($noAdjacentHook == 1)
				{
					print "<li>Otherwise, <a href=\"#p".$nextCanonPage."\">turn to page ".$nextCanonPage."</a>.</li>";
				}
			}

		}
		
		print "</ul>";
	}
	
	?>
	</body>
	<?
		
	function queueEntry($entries,$currentEntry,$hooks)
	{		
		// if this starts with a quotemark, it was from the end of the previous sentence: drop it
		if (substr($currentEntry,0,1) == "\"")
		{
			$currentEntry  = ltrim($currentEntry,"\"");
			//$entries[sizeof($entries)-1]["text"] = $entries[sizeof($entries)-1]["text"]."\"";
		}
		
		// if the first line has an odd number of quotemarks, we're almost certainly missing one at the start
		if (preg_match("/^([^\"][^\n]+\")\n/",$currentEntry,$matches))
		{
			if (substr_count($matches[1],"\"") % 2 == 1)
			{ $currentEntry = "\"".ltrim($currentEntry); }
		}
		
		// if an odd number of quotes, we probably cut off mid-paragraph: add one to the end
		if (substr_count($currentEntry,"\"") % 2 == 1) { $currentEntry .= "\""; }
		
		$currentEntry  = ltrim($currentEntry," ')\n");
		array_push($entries,array("order"=>sizeof($entries),"text"=>$currentEntry,"hooks"=>$hooks));
		
		return $entries;
	}

	function frameChoice($text)
	{		
		$text = preg_replace("/^(can|do to|will|may|did) /","",$text);
		$text = preg_replace("/^(don't|do not|can't|cannot|won't) /","not ",$text);
		$text = preg_replace("/\byou\b/","yourself",$text);
		$text = preg_replace("/\bmy\b/","your",$text);
		$text = preg_replace("/\bmine\b/","yours",$text);
		$text = preg_replace("/\bmyself\b/","yourself",$text);
		$text = preg_replace("/\bme\b/","you",$text);
		$text = preg_replace("/\bI\b/","you",$text);

		$text = preg_replace("/\bwe\b/","you",$text);
		$text = preg_replace("/\bour\b/","your",$text);
		$text = preg_replace("/\bourselves\b/","yourself",$text);
		$text = preg_replace("/\bus\b/","you",$text);

		// present-tense the rest of the sentence as best we can
		$words = explode(" ",$text);
		$newtext = "";
		$previousword = "";
		foreach ($words as $word)
		{
			if (preg_match("/^(be|a|an|the)$/",$previousword)) // probably an adjective rather than a verb if it follows these words
			{
				$newtext .= $word." ";
			}
			else
			{
				$verb = presentVerb($word);
				if ($verb == "") { $verb = $word; }
				$newtext .= $verb." ";
			}
			$previousword = $word;
		}

		// strip the occasional "Mr" from the end
		$newtext = preg_replace("/,? Mr *$/","",$newtext);
		// can end it at an emdash if the sentence is very short
		$newtext = preg_replace("/(.{10,})--.+$/","$1",$newtext);
		
		return trim($newtext," \n\r-,");
	}
?>