<?php
declare(strict_types=1);

/**
 * Regression tests: media metadata and durable Wiki file writes.
 * Everything lives in a temporary folder; no production files are touched.
 */
$root=dirname(__DIR__);
$dir=sys_get_temp_dir().'/sunkora-wiki-files-'.bin2hex(random_bytes(8));
if(!mkdir($dir,0700))throw new RuntimeException('Failed to create test folder');
$count=0;
$check=static function(bool $condition,string $message)use(&$count):void{
 $count++; if(!$condition)throw new RuntimeException('FAILED: '.$message);
};
$reject=static function(callable $operation,string $message)use(&$count):void{
 $count++;try{$operation();}catch(DomainException $expected){return;}
 throw new RuntimeException('FAILED: accepted invalid file: '.$message);
};
function png_chunk(string $type,string $data):string{
 return pack('N',strlen($data)).$type.$data.pack('N',crc32($type.$data));
}
function sample_png(int $width=10,int $height=10):string{
 $header=pack('NNCCCCC',$width,$height,8,2,0,0,0);
 $scanline="\0".str_repeat("\xff\0\0",$width);
 $raster=str_repeat($scanline,min(10,$height));
 $text="Description\0".str_repeat('S',180);
 return "\x89PNG\r\n\x1a\n".png_chunk('IHDR',$header).png_chunk('tEXt',$text).
  png_chunk('IDAT',gzcompress($raster)).png_chunk('IEND','');
}

try{
 putenv('SUNKORA_WIKI_STORAGE_DIR='.$dir);
 require $root.'/Вики/app/core.php';
 $check(wiki_read()===null,'empty Wiki is not a false database');
 $file=$dir.'/valid.png';
 file_put_contents($file,sample_png());
 $size=filesize($file);
 $check(is_int($size)&&$size>100,'sample PNG has realistic dimensions and size');
 $check(wiki_validate_image_file($file,$size)==='png','valid PNG accepted');
 $reject(fn()=>wiki_validate_image_file($file,$size-1),'mismatched claimed upload size');
 $reject(fn()=>wiki_validate_image_file($file,5*1024*1024+1),'claimed size larger than actual file');
 file_put_contents($dir.'/not-image.jpg',str_repeat('A',256));
 $reject(fn()=>wiki_validate_image_file($dir.'/not-image.jpg',256),'fake JPEG extension');
 file_put_contents($dir.'/empty.png','test');
 $reject(fn()=>wiki_validate_image_file($dir.'/empty.png',4),'truncated file');
 file_put_contents($dir.'/huge.png',sample_png(8000,8000));
 $reject(fn()=>wiki_validate_image_file($dir.'/huge.png',filesize($dir.'/huge.png')),'oversized pixel count');
 file_put_contents($dir.'/wide.png',sample_png(8001,1));
 $reject(fn()=>wiki_validate_image_file($dir.'/wide.png',filesize($dir.'/wide.png')),'oversized width');

 $initial=['installed'=>true,'admin'=>['password_hash'=>'test'],'articles'=>[],'media'=>[]];
 wiki_change(function(&$db)use($initial):void{$db=$initial;});
 $stored=file_get_contents(WIKI_DATA);
 $check(is_string($stored)&&$stored!==''&&!str_contains($stored,'.wiki-'),'database written');
 $check(wiki_read()===$initial,'round-trip data preserved');

 try{
  wiki_change(function(&$db):void{$db['media'][]=['filename'=>'bad.png'];throw new DomainException('Canceled');});
  throw new LogicException('Failed write unexpectedly succeeded');
 }catch(DomainException $expected){}
 $check(file_get_contents(WIKI_DATA)===$stored,'failed operation leaves database unchanged');

 wiki_change(function(&$db):void{$db['media'][]=['filename'=>'image.png'];});
 $check(count(wiki_read()['media'])===1,'successful update persisted');
 $check(count(glob($dir.'/.wiki-*')?:[])===0,'temporary files cleaned up');
 echo "OK: $count Wiki media and storage assertions\n";
}finally{
 putenv('SUNKORA_WIKI_STORAGE_DIR');
 foreach(glob($dir.'/*')?:[] as $p)if(is_file($p))unlink($p);
 foreach(glob($dir.'/.*')?:[] as $p)if(is_file($p))unlink($p);
 rmdir($dir);
}
