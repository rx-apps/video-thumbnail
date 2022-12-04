<?php

class Video_thumbnailController extends Video_thumbnail
{
	/**
	 * 게시글이 작성될 때 호출됩니다.
	 * 
	 * @param object $obj
	 * @return bool
	 */
	public function triggerAfterInsertDocument ($obj): bool
	{
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument($obj->document_srl);
		$content = $oDocument->getContentPlainText();

		$oModel = $this->getModel();
		$urls = $oModel->getVideoUrlsFromString($content);
		
		$oFileController = getController('file');
		foreach ($urls as $url)
		{
			$imageData = $oModel->getThumbnailImageData($url);
			if ($imageData === null)
			{
				continue;
			}
			
			$fileInfo = $oModel->getFileInfoFromImageData($imageData);
			$oFileController->insertFile($fileInfo, $obj->module_srl, $obj->document_srl, 0, true);
		}
		
		return true;
	}
}
