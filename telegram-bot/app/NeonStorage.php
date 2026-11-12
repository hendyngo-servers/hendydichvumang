<?php
declare(strict_types=1);
namespace Hendy;
use RuntimeException;
/** Private Neon Object Storage bridge; S3 keys stay inside Neon Functions. */
final class NeonStorage
{
    public static function configured(): bool
    {
        $url=trim((string)Env::get('NEON_STORAGE_FUNCTION_URL',''));
        $key=trim((string)Env::get('NEON_STORAGE_FUNCTION_KEY',''));
        if($url===''&&$key==='') return false;
        if($url===''||$key==='') throw new RuntimeException('Neon Object Storage chưa được cấu hình đầy đủ.');
        $parts=parse_url($url);
        if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'
            ||!str_contains(strtolower((string)($parts['host']??'')),'.compute.')
            ||!str_ends_with(strtolower((string)($parts['host']??'')),'.neon.tech'))
            throw new RuntimeException('NEON_STORAGE_FUNCTION_URL không phải URL Neon Function hợp lệ.');
        if(strlen($key)<32||strlen($key)>256) throw new RuntimeException('NEON_STORAGE_FUNCTION_KEY phải dài từ 32 đến 256 ký tự.');
        return true;
    }
    public static function uploadFile(string $localPath,string $objectKey,string $contentType): void
    {
        if(!is_file($localPath)||!is_readable($localPath)) throw new RuntimeException('Không đọc được tệp cần tải lên.');
        if(!self::validObjectKey($objectKey)) throw new RuntimeException('Đường dẫn tệp lưu trữ không hợp lệ.');
        if(!preg_match('#\A(?:application/json|text/plain|text/css|text/html|image/svg\+xml|image/png|image/jpeg|image/webp)\z#',$contentType))
            throw new RuntimeException('Loại tệp không được hỗ trợ cho Neon Storage.');
        $signed=self::requestPresignedUrl('upload',$objectKey,$contentType);
        $parts=parse_url($signed['url']);
        if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'
            ||!str_contains(strtolower((string)($parts['host']??'')),'.storage.')
            ||!str_ends_with(strtolower((string)($parts['host']??'')),'.neon.tech'))
            throw new RuntimeException('Neon Storage trả về URL tải lên không hợp lệ.');
        $stream=fopen($localPath,'rb');
        if($stream===false) throw new RuntimeException('Không mở được tệp tải lên.');
        $curl=curl_init($signed['url']);
        if($curl===false){fclose($stream);throw new RuntimeException('Không khởi tạo được kết nối Neon Storage.');}
        try{
            curl_setopt_array($curl,[
                CURLOPT_UPLOAD=>true,CURLOPT_INFILE=>$stream,CURLOPT_INFILESIZE=>filesize($localPath),
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: '.$contentType,'Expect:'],
                CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>45,CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,
            ]);
            $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
            if($response===false||$status<200||$status>=300) throw new RuntimeException('Không thể lưu tệp vào Neon Object Storage (HTTP '.$status.').');
        }finally{curl_close($curl);fclose($stream);}
    }
    public static function presignedDownloadUrl(string $objectKey,int $expiresIn=300): string
    {
        if(!self::validObjectKey($objectKey)) throw new RuntimeException('Đường dẫn tệp lưu trữ không hợp lệ.');
        $signed=self::requestPresignedUrl('download',$objectKey,'',$expiresIn);
        $parts=parse_url($signed['url']);
        if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'
            ||!str_contains(strtolower((string)($parts['host']??'')),'.storage.')
            ||!str_ends_with(strtolower((string)($parts['host']??'')),'.neon.tech'))
            throw new RuntimeException('Neon Storage trả về URL tải xuống không hợp lệ.');
        return $signed['url'];
    }
    private static function validObjectKey(string $key): bool
    {
        return strlen($key)<=1024&&preg_match('#\Amodules/[a-z][a-z0-9-]{1,62}/[A-Za-z0-9_-]{16,64}\.(?:json|md|txt|css|html|svg|png|jpg|jpeg|webp)\z#',$key)===1;
    }
    private static function requestPresignedUrl(string $action,string $objectKey,string $contentType,int $expiresIn=300): array
    {
        if(!self::configured()) throw new RuntimeException('Neon Object Storage chưa được cấu hình.');
        $baseUrl=rtrim((string)Env::get('NEON_STORAGE_FUNCTION_URL'),'/');
        $apiKey=(string)Env::get('NEON_STORAGE_FUNCTION_KEY');
        $payload=json_encode(['action'=>$action,'key'=>$objectKey,'contentType'=>$contentType,'expiresIn'=>max(1,min(900,$expiresIn))],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $curl=curl_init($baseUrl.'/presign');
        if($curl===false) throw new RuntimeException('Không khởi tạo được yêu cầu tới Neon Function.');
        try{
            curl_setopt_array($curl,[
                CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$apiKey,'Content-Type: application/json','Accept: application/json'],
                CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_SSL_VERIFYPEER=>true,
                CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,
            ]);
            $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
            if($response===false||$status<200||$status>=300) throw new RuntimeException('Neon Function không cấp được URL lưu trữ (HTTP '.$status.').');
            $result=json_decode((string)$response,true,16,JSON_THROW_ON_ERROR);
            if(!is_array($result)||!is_string($result['url']??null)||!is_array($result['headers']??null)||!is_string($result['method']??null))
                throw new RuntimeException('Phản hồi Neon Function không đúng định dạng.');
            return $result;
        }catch(\JsonException $error){throw new RuntimeException('Neon Function trả về dữ liệu không hợp lệ.',0,$error);}
        finally{curl_close($curl);}
    }
}
