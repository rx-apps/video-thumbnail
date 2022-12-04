<?php

class Video_thumbnailController extends Video_thumbnail
{
	/**
	 * 게시글이 작성될 때 호출됩니다.
	 *
	 * @param object $obj
	 * @param bool $isUpdate
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function triggerAfterInsertDocument ($obj, bool $isUpdate = false): bool
	{
		$oModel = $this->getModel();
		$urls = $oModel->getVideoUrlsFromString($obj->content);
		
		if ($isUpdate)
		{
			$videoThumbnails = $oModel->getVideoThumbnailsFromDocumentSrl($obj->document_srl);
			$insertedVideoUrls = array_map(static function ($videoThumbnail) {
				return $videoThumbnail->video_url;
			}, $videoThumbnails);
		}
		
		$oFileController = getController('file');
		foreach ($urls as $url)
		{
			if ($isUpdate && in_array($url, $insertedVideoUrls))
			{
				$insertedVideoUrls = array_diff($insertedVideoUrls, [ $url ]);
				continue;
			}
			
			$imageData = $oModel->getThumbnailImageData($url);
			if ($imageData === null)
			{
				continue;
			}
			
			$fileInfo = $oModel->getFileInfoFromImageData($imageData);
			$file = $oFileController->insertFile($fileInfo, $obj->module_srl, $obj->document_srl, 0, true);
			$oModel->insertVideoThumbnail($file->variables['file_srl'], $obj->document_srl, $url);
		}
		
		if ($isUpdate)
		{
			$fileSrls = array_reduce($videoThumbnails, static function (array $carry, $videoThumbnail) use ($insertedVideoUrls) {
				if (in_array($videoThumbnail->video_url, $insertedVideoUrls))
				{
					$carry[] = $videoThumbnail->file_srl;
				}
				return $carry;
			}, []);
			
			if (empty($fileSrls))
			{
				return true;
			}
			
			$oFileController->deleteFile($fileSrls);
			$oModel->deleteVideoThumbnails($fileSrls);
		}
		
		return true;
	}

	/**
	 * 게시글이 수정될 때 호출됩니다.
	 * 
	 * @param object $obj
	 * @return bool
	 * @noinspection PhpUnused
	 */
	public function triggerAfterUpdateDocument ($obj): bool
	{
		return $this->triggerAfterInsertDocument($obj, true);
	}
}
