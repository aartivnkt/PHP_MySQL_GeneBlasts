<?php
set_time_limit(0);
//create a list of databases
if(!$handle = fopen("/home/aarti/Desktop/new_beeblast/data/databases1.txt","r"))
{
        $databaseFileError = true;
}

while(!feof($handle))
{
        $line = fgets($handle);

        $trimmed = trim($line);
        if(!empty($trimmed))
        {
                $tempArray = split("\n",$trimmed);
		$db_names[]=$tempArray[0];

        }
                

}
fclose($handle);
print_r ($db_names);

include_once("databaseLogin.php");

$link = mysql_connect($host, $user, $password) or die('Could not connect: ' . mysql_error());
mysql_select_db('trial1') or die('Could not select database<BR>');



$query = "SELECT SeqName,FastaSequence FROM Sequence WHERE Proj_id = 15 limit 1";

//$query = "SELECT SeqName,FastaSequence FROM Sequence WHERE Proj_id = 15";

$result = mysql_query($query);

$recipOut = fopen("tmp/recipOut.txt","w"); //mainOut FH changed to recipOut

$count = 0;

foreach($db_names as $db)
{
        fwrite($recipOut,$db. "\n");
}
fwrite($recipOut,"\n");

while($row = mysql_fetch_array($result,MYSQL_ASSOC))
{
 
$count++;
system("rm tmp/temp.fasta");

$beegene_name = preg_replace("/(GB.+)\s?;\s.+/","$1",$row['SeqName']);

if(preg_match('/(GB.+);.+/',$beegene_name))  //second check for removing ; in GB..;...
{
$beegene_name = preg_replace("/(GB.+)\s?;\s?.+/","$1",$beegene_name);
}


echo "$beegene_name\n";


$Gene_Hit = fopen("recipBlastOutE/".$beegene_name.".fasta","w"); //blastout changed to $Gene_Hit , save the output file with name of bee gene

$handle = fopen("tmp/temp.fasta","w"); //a fasta file for the bee gene...
fwrite($handle,">".$beegene_name."\n");

fwrite($handle,$row['FastaSequence']);
fclose($handle);

        //record the initial sequence
        fwrite($Gene_Hit,">".$beegene_name. "\n"); 
        fwrite($Gene_Hit,$row['FastaSequence'] . "\n");

//        echo "$beegene_name\n"; //purely for debuggin, print out the bee gene name

        fwrite($recipOut,$beegene_name); //write beegene_name to final output file
        fwrite($recipOut,"\n");
        $recip_hitCount = 0; //count the number of reciprocal blast matches



//First Blast Starts:

foreach($db_names as $db)
        {
                if($db == "Api_mellif")
                {
                        continue;
                }
                $command = "blastall -p " . "blastn" . " -i tmp/temp.fasta -d " . "/home/aarti/Desktop/renamed_seqs/$db -o tmp/hom.out -b 100 -v 100 -e 1e-6";//
                system($command);
                echo "command being run" . $command . "\n";

$handle = fopen("tmp/hom.out","r");

$perlcmd = "parseblast_mHSP_new.pl -f tmp/hom.out -o tmp/$beegene_name.parseblast";

system("perl $perlcmd");

echo "command being run". $perlcmd . "\n";

$perlHandle = fopen("tmp/$beegene_name.parseblast","r");

$numHit = 0;

$genehit= array();

while(!feof($perlHandle))
{
        $pline = fgets($perlHandle);

if(preg_match("/(GB.+?)\s.+/",$pline,$matches))
{
$query = $matches[1];  //record name of the beegene/query
}

if(preg_match("/($db.+?)\s.+/",$pline,$matches))
{
$numHit++;
//print "$matches[1]\n";
$genehit[$numHit]['SeqName'] = $matches[1]; //record the name of the hits
}

if(preg_match("/$db.+?\s(\d+e?-?\.?\d+)\s.+/",$pline,$matches))
{
echo "E value:".$matches[1]."\n";
$genehit[$numHit]['Evalue']= $matches[1]; //record the Evalue of each hit
}

if(preg_match("/(1|-1?)\s.+/",$pline,$matches))
{
$genehit[$numHit]['Strand']= $matches[1];  //record the strand

        if($matches[1] == -1)
        {
      
  
		$genehit[$numHit]['Direction'] = 1; //record the direction of hit on complementary strand


                if(preg_match("/-1\s+\d+\.\d+\s+\d+\s+\d+\s+(\d+)\s+(\d+)/",$pline,$matches))
                {
                echo "1 Position of match:$matches[1]\n";
                echo "2 position of match :$matches[2]\n";              
 
		$genehit[$numHit]['Start'] = $matches[1];   //record the start position of the hit
                $genehit[$numHit]['Stop'] = $matches[2];   //record the stop position of the hit

                }
	}
        else if($matches[1] == 1)
        {

		$genehit[$numHit]['Direction'] = 0;      //record the direction of hit on the forward strand

                if(preg_match("/1?\s+\d+\.\d+\s+\d+\s+\d+\s+(\d+)\s+(\d+)/",$pline,$matches))
                {
                echo "1 Position of match:$matches[1]\n";
                echo "2 position of match :$matches[2]\n";

		$genehit[$numHit]['Start'] = $matches[1];   //record the start position of the hit
                $genehit[$numHit]['Stop'] = $matches[2];   //record the stop position of the hit

                }


        }



	else { }

}

if(preg_match("/1\s+(\d+\.\d+?)\s.+/",$pline,$matches))
{
//echo "$matches[1]\n";
$genehit[$numHit]['PID']= $matches[1]; //record the PID of each hit

        if(abs ($matches[1]- $genehit[1]['PID'] ) > 10) //check if PID > 10 from the top hit && if yes, discard the hit
        {
        $numHit--;
        }

echo "Identity percent:".$matches[1]."\n";


}

}

fclose($perlHandle);

print "Next gng to for loop:\n";

print "NumHit is $numHit\n";

$handle = fopen("tmp/temp2.fasta","w");


$start = array();
$stop = array();


for($k=1;$k<=$numHit;$k++)
{
$seqlength =  abs($genehit[$k]['Stop'] - $genehit[$k]['Start']);
print "Seqlength is :".$seqlength."\n";

        if($genehit[$k]['Direction'] == 1)	
	{
	$query = "SELECT REVERSE(SUBSTRING(FastaSequence,".$genehit[$k]['Start'].",$seqlength)) FROM Sequence WHERE SeqName = '".$genehit[$k]['SeqName']."'";	     
	//take the complement of the above seq  in the while loop

	}
   

    
        else if($genehit[$k]['Direction'] == 0)
        {
	$query = "SELECT SUBSTRING(FastaSequence,".$genehit[$k]['Start'].",$seqlength) FROM Sequence WHERE SeqName = '".$genehit[$k]['SeqName']."'";
	}

       else { }

$result2 = mysql_query($query) or die("Error in select: " . mysql_error());

while($row = mysql_fetch_array($result2,MYSQL_BOTH))
{

$genehit[$k]['Sequence']= $row;

//check for the direction and take the complement

}



/*  ONLY FOR DEBUGGING

print_r($genehit[$k]['SeqName']);
print "\n";
print_r($genehit[$k]['Sequence']);
print"\n";

*/


//NOW DO THE SORTING:


array_push($start,$genehit[$k]['Start']);
array_push($stop,$genehit[$k]['Stop']);
                       
}


/*
print_r($start);
print "\n\n";
print_r($stop);
print "\n\n";
*/

if(count($start)== 1)
{

print_r($genehit[1]['SeqName']);
fwrite($handle ,">".$genehit[1]['SeqName']."\n");
//print "BYTES:::$bytes\n";

print_r($genehit[1]['Sequence']);
fwrite($handle, $genehit[1]['Sequence']."\n");
print "only one seq\n";
}

if($numHit > 1)
{

for($k=1;$k <= $numHit-1;$k++)
{
if($genehit[$k]['Stop'] < $genehit[$k+1]['Start'])
{
$padding = $genehit[$k]['Stop'] - $genehit[$k+1]['Start'];
fwrite($handle , $genehit[$k]['Sequence'].$padding.$genehit[$k+1]['Sequence']."\n");
}
}

}





	} //foreach close


} //main while close
