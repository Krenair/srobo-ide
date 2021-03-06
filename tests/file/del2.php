<?php

$config = Configuration::getInstance();
$config->override("repopath", $testWorkPath);
$config->override("user.default", "bees");
$config->override("user.default.teams", array(1, 2));
$config->override("auth_module", "single");
$config->override("modules", array("file", 'proj'));

//do a quick authentication
$auth = AuthBackend::getInstance();
test_true($auth->authUser('bees','face'), "authentication failed");

//setup the required input keys
$input = Input::getInstance();
$input->setInput("team", 1);
$input->setInput("project", "monkies");

$output = Output::getInstance();

$mm = ModuleManager::getInstance();
$mm->importModules();

$file = $mm->getModule('file');
$proj = $mm->getModule('proj');
test_nonnull($file, 'file module does not exist');
test_nonnull($proj, 'proj module does not exist');

function getRepoPath()
{
	$config = Configuration::getInstance();
	$input = Input::getInstance();
	return $config->getConfig("repopath") . "/" . $input->getInput("team") . "/users/bees/" . $input->getInput("project");
}

$repopath = getRepoPath();

$projectManager = ProjectManager::getInstance();
$projectManager->createRepository($input->getInput("team"), $input->getInput("project"));
$repo = $projectManager->getUserRepository($input->getInput("team"), $input->getInput("project"), 'bees');
test_true(is_dir($repopath), "created repo did not exist");

section('delete multiple files');
subsection('create the files');
$fileNames = array('first.py', 'second.py', 'pound-£-pony-♞-file.py');
foreach ($fileNames as $name)
{
	$repo->putFile($name, "$name content");
	$repo->stage($name);
}
$repo->commit('create', 'bees', 'bees@sr.org');

subsection('delete the files');
$input->setInput('files', $fileNames);
test_true($file->dispatchCommand('del'), "Failed to dispatch deletion");

foreach ($fileNames as $name)
{
	$abspath = "$repopath/$name";
	test_nonexistent($abspath, "file/del failed to remove $abspath, before commit");
}

subsection('commit');
$input->setInput('paths', $fileNames);
$input->setInput('message', "Delete all 3 files.");
test_true($proj->dispatchCommand('commit'), "Failed to commit removal of the 3 files.");

foreach ($fileNames as $name)
{
	$abspath = "$repopath/$name";
	test_nonexistent($abspath, "file/del failed to remove $abspath, after commit");
}

subsection("get the file-list to check that it's really gone");
$input->setInput('path', '.');
test_true($file->dispatchCommand('list'), "Failed to get file list after removing files.");
$list = $output->getOutput('files');

foreach ($fileNames as $name)
{
	test_false(in_array($name, $list), "File '$name' listed after committing its removal.");
	$abspath = "$repopath/$name";
	test_nonexistent($abspath, "file/del failed to remove $abspath, after commit");
}

section('undelete multiple files');
subsection("'checkout' the files to a specific older revision (HEAD^)");
$input->setInput('revision', 'HEAD^');
$input->setInput('files', $fileNames);
test_true($file->dispatchCommand('co'), "Failed to checkout files to older revision.");

subsection('Check they exist before commit');
foreach ($fileNames as $name)
{
	$abspath = "$repopath/$name";
	test_existent($abspath, "file/co failed to restore $abspath, before commit");
}

subsection('commit');
$input->setInput('paths', $fileNames);
$input->setInput('message', "Restore all 3 files.");
test_true($proj->dispatchCommand('commit'), "Failed to commit restoration of the 3 files.");

foreach ($fileNames as $name)
{
	$abspath = "$repopath/$name";
	test_existent($abspath, "file/co failed to restore $abspath, after commit");
}

subsection("get the file-list to check they're really back");
$input->setInput('path', '.');
test_true($file->dispatchCommand('list'), "Failed to get file list after restoring files.");
$list = $output->getOutput('files');

foreach ($fileNames as $name)
{
	test_true(in_array($name, $list), "File '$name' not listed after committing its restoration.");
	$abspath = "$repopath/$name";
	test_existent($abspath, "file/co failed to restore $abspath, after commit");
}
