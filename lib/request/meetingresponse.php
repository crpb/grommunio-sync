<?php
/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * SPDX-FileCopyrightText: Copyright 2007-2016 Zarafa Deutschland GmbH
 * SPDX-FileCopyrightText: Copyright 2020-2022 grommunio GmbH
 *
 * Provides the MEETINGRESPONSE command
 */

class MeetingResponse extends RequestProcessor {
	/**
	 * Handles the MeetingResponse command.
	 *
	 * @param int $commandCode
	 *
	 * @return bool
	 */
	public function Handle($commandCode) {
		$requests = [];

		if (!self::$decoder->getElementStartTag(SYNC_MEETINGRESPONSE_MEETINGRESPONSE)) {
			return false;
		}

		while (self::$decoder->getElementStartTag(SYNC_MEETINGRESPONSE_REQUEST)) {
			$req = [];
			WBXMLDecoder::ResetInWhile("meetingResponseRequest");
			while (WBXMLDecoder::InWhile("meetingResponseRequest")) {
				if (self::$decoder->getElementStartTag(SYNC_MEETINGRESPONSE_USERRESPONSE)) {
					$req["response"] = self::$decoder->getElementContent();
					if (!self::$decoder->getElementEndTag()) {
						return false;
					}
				}

				if (self::$decoder->getElementStartTag(SYNC_MEETINGRESPONSE_FOLDERID)) {
					$req["folderid"] = self::$decoder->getElementContent();
					if (!self::$decoder->getElementEndTag()) {
						return false;
					}
				}

				if (self::$decoder->getElementStartTag(SYNC_MEETINGRESPONSE_REQUESTID)) {
					$req["requestid"] = self::$decoder->getElementContent();
					if (!self::$decoder->getElementEndTag()) {
						return false;
					}
				}

				if (self::$decoder->getElementStartTag(SYNC_MEETINGRESPONSE_INSTANCEID)) {
					$req["instanceid"] = self::$decoder->getElementContent();
					if (!self::$decoder->getElementEndTag()) {
						return false;
					}
				}

				if (self::$decoder->getElementStartTag(SYNC_MEETINGRESPONSE_SENDRESPONSE)) {
					if (self::$decoder->getElementStartTag(SYNC_AIRSYNCBASE_BODY)) {
						if (self::$decoder->getElementStartTag(SYNC_AIRSYNCBASE_TYPE)) {
							$req["bodytype"] = self::$decoder->getElementContent();
							if (!self::$decoder->getElementEndTag()) {
								return false;
							}
						}
						if (self::$decoder->getElementStartTag(SYNC_AIRSYNCBASE_DATA)) {
							$req["body"] = self::$decoder->getElementContent();
							if (!self::$decoder->getElementEndTag()) {
								return false;
							}
						}
						if (!self::$decoder->getElementEndTag()) {
							return false;
						}
					} // end body
					if (self::$decoder->getElementStartTag(SYNC_MEETINGRESPONSE_PROPOSEDSTARTTIME)) {
						$req["proposedstarttime"] = self::$decoder->getElementContent();
						if (!self::$decoder->getElementEndTag()) {
							return false;
						}
					}
					if (self::$decoder->getElementStartTag(SYNC_MEETINGRESPONSE_PROPOSEDENDTIME)) {
						$req["proposedendtime"] = self::$decoder->getElementContent();
						if (!self::$decoder->getElementEndTag()) {
							return false;
						}
					}
				} // end send response

				$e = self::$decoder->peek();
				if ($e[EN_TYPE] == EN_TYPE_ENDTAG) {
					self::$decoder->getElementEndTag();

					break;
				}
			}
			array_push($requests, $req);
		}

		if (!self::$decoder->getElementEndTag()) {
			return false;
		}

		// output the error code, plus the ID of the calendar item that was generated by the
		// accept of the meeting response
		self::$encoder->StartWBXML();
		self::$encoder->startTag(SYNC_MEETINGRESPONSE_MEETINGRESPONSE);

		foreach ($requests as $req) {
			$status = SYNC_MEETRESPSTATUS_SUCCESS;

			try {
				$backendFolderId = self::$deviceManager->GetBackendIdForFolderId($req["folderid"]);

				// if the source folder is an additional folder the backend has to be setup correctly
				if (!self::$backend->Setup(GSync::GetAdditionalSyncFolderStore($backendFolderId))) {
					throw new StatusException(sprintf("HandleMoveItems() could not Setup() the backend for folder id %s/%s", $req["folderid"], $backendFolderId), SYNC_MEETRESPSTATUS_SERVERERROR);
				}

				$calendarid = self::$backend->MeetingResponse($backendFolderId, $req);
				if ($calendarid === false) {
					throw new StatusException("HandleMeetingResponse() not possible", SYNC_MEETRESPSTATUS_SERVERERROR);
				}
			}
			catch (StatusException $stex) {
				$status = $stex->getCode();
			}

			self::$encoder->startTag(SYNC_MEETINGRESPONSE_RESULT);
			self::$encoder->startTag(SYNC_MEETINGRESPONSE_REQUESTID);
			self::$encoder->content($req["requestid"]);
			self::$encoder->endTag();

			self::$encoder->startTag(SYNC_MEETINGRESPONSE_STATUS);
			self::$encoder->content($status);
			self::$encoder->endTag();

			if ($status == SYNC_MEETRESPSTATUS_SUCCESS && !empty($calendarid)) {
				self::$encoder->startTag(SYNC_MEETINGRESPONSE_CALENDARID);
				self::$encoder->content($calendarid);
				self::$encoder->endTag();
			}
			self::$encoder->endTag();
			self::$topCollector->AnnounceInformation(sprintf("Operation status %d", $status), true);
		}
		self::$encoder->endTag();

		return true;
	}
}
