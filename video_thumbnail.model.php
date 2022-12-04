<?php

class Video_thumbnailModel extends Video_thumbnail
{
	/**
	 * 썸네일 이미지 데이터를 획득합니다.
	 * 
	 * @param string $videoUrl
	 * @return ?array{mime: string, raw: string}
	 */
	public function getThumbnailImageData (string $videoUrl): ?array
	{
		$thumbnailUrl = $this->getThumbnailUrl($videoUrl);
		if ($thumbnailUrl === null)
		{
			return null;
		}
		
		return $this->getRemoteImageData($thumbnailUrl);
	}
	
	/**
	 * 썸네일 URL을 획득합니다.
	 * 
	 * @param string $videoUrl
	 * @return ?string
	 */
	public function getThumbnailUrl (string $videoUrl): ?string
	{
		$service = $this->getVideoService($videoUrl);
		switch ($service)
		{
			case 'youtube':
				return $this->getYoutubeThumbnailUrl($videoUrl);
		}
		
		return null;
	}

	/**
	 * 영상 URL에서 서비스명을 획득합니다.
	 * 
	 * @param string $videoUrl
	 * @return ?string
	 */
	public function getVideoService (string $videoUrl): ?string
	{
		if (strpos($videoUrl, '://') !== false)
		{
			$videoUrl = explode('://', $videoUrl)[1];
		}
		
		$hostname = explode('/', $videoUrl, 2)[0];
		
		switch ($hostname)
		{
			case 'youtube.com':
			case 'www.youtube.com':
			case 'youtube-nocookie.com':
			case 'www.youtube-nocookie.com':
			case 'youtu.be':
			case 'www.youtu.be':
				return 'youtube';
		}
		
		return null;
	}

	/**
	 * 유튜브 URL에서 썸네일 이미지 URL을 획득합니다.
	 * 
	 * @param string $videoUrl
	 * @return ?string
	 */
	private function getYoutubeThumbnailUrl (string $videoUrl): ?string
	{
		$segments = explode('/', $videoUrl);
		$lastSegment = end($segments);
		if (strpos($lastSegment, 'v=') !== false)
		{
			$lastSegment = explode('&', explode('v=', $lastSegment)[1])[0];
		}
		
		return 'https://img.youtube.com/vi/' . $lastSegment . '/0.jpg';
	}

	/**
	 * 원격지에서 이미지 데이터를 획득합니다.
	 * 
	 * @param string $imageUrl
	 * @return array{mime: string, raw: string}
	 */
	private function getRemoteImageData (string $imageUrl): array
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $imageUrl);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		
		$output = curl_exec($ch);
		curl_close($ch);
		
		[ $header, $raw ] = explode("\r\n\r\n", $output, 2);
		
		$mime = 'text/plain';
		$headers = explode("\n", $header);
		foreach ($headers as $header)
		{
			[ $key, $value ] = explode(':', $header, 2);
			if (strtolower(trim($key)) == 'content-type')
			{
				$mime = trim($value);
				break;
			}
		}
		
		return [
			'mime' => $mime,
			'raw' => $raw
		];
	}

	/**
	 * 문자열에서 영상 URL 목록을 획득합니다.
	 *
	 * @param string $content
	 * @return array<string>
	 */
	public function getVideoUrlsFromString (string $content): array
	{
		if (preg_match_all('/(?:https?:)?\/\/[^,\s()<>]+(?:\(\w+\)|([^,[:punct:]\s]|\/))/', $content, $matches) === 0)
		{
			return [];
		}

		return $matches[0];
	}

	/**
	 * 이미지 데이터를 PHP의 $_FILES 스펙으로 변환합니다.
	 * 
	 * @param array{mime: string, raw: string} $imageData
	 * @return array{name: string, tmp_name: string, size: int, error: int}
	 */
	public function getFileInfoFromImageData (array $imageData): array
	{
		$extension = $this->getFileExtensionFromMimeType($imageData['mime']);
		$fileName = sha1($imageData['raw']) . '.' . $extension;
		
		$tmpFilePath = sys_get_temp_dir() . '/' . $fileName;
		file_put_contents($tmpFilePath, $imageData['raw']);
		
		return [
			'name' => $fileName,
			'tmp_name' => $tmpFilePath,
			'size' => strlen($imageData['raw']),
			'error' => UPLOAD_ERR_OK
		];
	}

	/**
	 * MIME 타입를 확장자로 변환합니다.
	 * 
	 * @param string $mimeType
	 * @return string
	 */
	private function getFileExtensionFromMimeType (string $mimeType): string
	{
		switch ($mimeType)
		{
			case 'image/avif':
				return 'avif';
				
			case 'image/bmp':
				return 'bmp';
				
			case 'image/gif':
				return 'gif';
				
			case 'image/jpg':
			case 'image/jpeg':
				return 'jpg';
				
			case 'image/png':
				return 'png';
				
			case 'image/svg':
			case 'image/svg+xml':
				return 'svg';
				
			case 'image/webp':
				return 'webp';
		}
		
		return '';
	}

	/**
	 * DB에 썸네일 기록을 추가합니다.
	 *
	 * @param int $fileSrl
	 * @param int $documentSrl
	 * @param string $videoUrl
	 * @param string|null $regdate
	 * @return object
	 */
	public function insertVideoThumbnail (int $fileSrl, int $documentSrl, string $videoUrl, string $regdate = null)
	{
		return executeQuery('video_thumbnail.insertVideoThumbnail', [
			'file_srl' => $fileSrl,
			'document_srl' => $documentSrl,
			'video_url' => $videoUrl,
			'regdate' => $regdate
		]);
	}

	/**
	 * DB에서 썸네일 기록들을 게시글 번호로 검색한 결과를 반환합니다.
	 * 
	 * @param int $documentSrl
	 * @return array<object{file_srl: int, document_srl: int, video_url: string, regdate: string}>
	 */
	public function getVideoThumbnailsFromDocumentSrl (int $documentSrl): array
	{
		$output = executeQueryArray('video_thumbnail.getVideoThumbnails', [
			'document_srl' => $documentSrl
		]);
		if (!$output->toBool())
		{
			return [];
		}
		
		return $output->data;
	}

	/**
	 * DB에서 파일 번호가 일치하는 썸네일 기록들을 삭제합니다.
	 * 
	 * @param $fileSrl
	 * @return object
	 */
	public function deleteVideoThumbnails ($fileSrl)
	{
		return executeQuery('video_thumbnail.deleteVideoThumbnails', [
			'file_srls' => $fileSrl
		]);
	}
}
