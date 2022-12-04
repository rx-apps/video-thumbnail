<?php

class Video_thumbnailModel extends Video_thumbnail
{
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
		
		return 'https://img.youtube.com/vi/' . $lastSegment . '/maxresdefault.jpg';
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
}
