<?php

/* Copyright (c) 2020 Nils Haagen <nils.haagen@concepts-and-training.de> Extended GPL, see docs/LICENSE */

use ILIAS\Setup;
use ILIAS\Refinery;
use ILIAS\UI;

class ilLearningSequenceSetupAgent implements Setup\Agent
{
    /**
     * @inheritdoc
     */
    public function hasConfig() : bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getArrayToConfigTransformation() : Refinery\Transformation
    {
        throw new \LogicException("Agent has no config.");
    }

    /**
     * @inheritdoc
     */
    public function getInstallObjective(Setup\Config $config = null) : Setup\Objective
    {
        $dir_objective = new ilFileSystemComponentDataDirectoryCreatedObjective(
            ilLearningSequenceFilesystem::PATH_PRE,
            ilFileSystemComponentDataDirectoryCreatedObjective::WEBDIR
        );
        return new Setup\ObjectiveCollection(
            "Complete objectives from Modules/LearningSequence",
            false,
            $dir_objective
        );
    }

    /**
     * @inheritdoc
     */
    public function getUpdateObjective(Setup\Config $config = null) : Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }

    /**
     * @inheritdoc
     */
    public function getBuildArtifactObjective() : Setup\Objective
    {
        return new Setup\Objective\NullObjective();
    }
}
