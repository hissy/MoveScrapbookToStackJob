<?php
defined('C5_EXECUTE') or die("Access Denied.");
class MoveScrapbookBlocksToStack extends Job {

	public function getJobName() {
		return t("Move blocks from legacy Scrapbooks to Stacks.");
	}
	
	public function getJobDescription() {
		return t("Move each blocks from dashboard/scrapbook to stack.");
	}
	
	public function run() {
		Cache::flush();
		
		//$l = new Log('move_scrapbook_to_stack', true);
 
		$db = Loader::db();
		Loader::model('stack/model');
		
		$scrapbookHelper = Loader::helper('concrete/scrapbook');
		$globalScrapbookPage = $scrapbookHelper->getGlobalScrapbookPage();
		if (!$globalScrapbookPage->getCollectionID()) {
			return t("No scrapbook page found.");
		}
		
		// Find all scrapbooks
		$availableScrapbooks = $scrapbookHelper->getAvailableScrapbooks();
		
		// For each scrapbook... (each scrapbook is an Area on the global scrapbook page)
		$blockCount = 0;
		$instanceCount = 0;
		foreach($availableScrapbooks as $scrapbook) {
		
			// Find all blocks in that scrapbook
			$scrapbookArea = new Area($scrapbook['arHandle']);
			$scrapbookBlocks = $scrapbookArea->getAreaBlocksArray($globalScrapbookPage);
			
			// For each block...
			foreach($scrapbookBlocks as $ix => $block) {
			
				// Get or create stack
				$stack = Stack::getByName($block->getBlockName());
				if (!$stack) { $stack = Stack::addStack($block->getBlockName()); }
				$stackArea = Area::get($stack, STACKS_AREA_NAME);
			
				// Move block to the stack
				$block->move($stack, $stackArea);
				
				// Find all instances of the original block
				$v = array($block->getBlockID());
				$q = "SELECT cvb.* FROM CollectionVersionBlocks AS cvb "
					. "LEFT JOIN CollectionVersions AS cv ON cvb.cvID = cv.cvID && cvb.cID = cv.cID "
					. "WHERE cvb.bID = ? && cv.cvIsApproved = 1";
				$r = $db->prepare($q);
				//$l->write("(".$r."),(".implode(",", $v).") / ");
				$res = $db->execute($r, $v);
				$originalBlockInstances = array();
				while ($row = $res->fetchRow()) {
					if ($row['cID'] != $stack->getCollectionID()) {
						$originalBlockInstances[] = $row;
					}
				}
				
				// For each instance of the block...
				foreach($originalBlockInstances as $originalBlockInstance) {
				
					// Create a proxy block pointing to the stack
					$v = array(BLOCK_HANDLE_STACK_PROXY);
					$q = "INSERT INTO Blocks (bID, bName, bDateAdded, bDateModified, bFilename, bIsActive, btID, uID) "
						. "VALUES (NULL, NULL, NOW(), NOW(), NULL, 1, (SELECT bt.btID FROM BlockTypes AS bt WHERE bt.btHandle = ?), 1)";
					$r = $db->prepare($q);
					//$l->write("(".$r."),(".implode(",", $v).") / ");
					$db->execute($r, $v);
					$proxyBlockID = $db->Insert_ID();
					
					// Add in proxy block content
					$v = array($proxyBlockID,$stack->getCollectionID());
					$q = "INSERT INTO btCoreStackDisplay (bID, stID) VALUES (?, ?)";
					$r = $db->prepare($q);
					//$l->write("(".$r."),(".implode(",", $v).") / ");
					$db->execute($r, $v);
					
					// Replace the original with the proxy
					$v = array($proxyBlockID, $originalBlockInstance['cID'], $originalBlockInstance['cvID'], $originalBlockInstance['bID'], $originalBlockInstance['arHandle']);
					$q = "UPDATE CollectionVersionBlocks AS cvb SET bID = ?, isOriginal = 1, cbOverrideAreaPermissions = 0 "
						. "WHERE cID = ? && cvID = ? && bID = ? && arHandle = ?";
					$r = $db->prepare($q);
					//$l->write("(".$r."),(".implode(",", $v).") / ");
					$db->execute($r, $v);
					
					$q = "UPDATE CollectionVersionBlockStyles AS cvbs SET bID = ? "
						. "WHERE cID = ? && cvID = ? && bID = ? && arHandle = ?";
					$r = $db->prepare($q);
					//$l->write("(".$r."),(".implode(",", $v).") / ");
					$db->execute($r, $v);
					
					$instanceCount++;
					
				}
				
				$blockCount++;
			
			} // end foreach($scrapbookBlocks)
		
		} // end foreach($availableScrapbooks)
		
		//$l->close();
		
		Cache::flush();
		return t("%d instances of %d blocks moved to stack.",$instanceCount,$blockCount);
	}
}

?>