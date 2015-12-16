<?php

require_once ($CFG->dirroot.'/lib/formslib.php');

class mod_journal_entry_form extends moodleform {

    function definition() {
        $this->_form->addElement('editor', 'text_editor', get_string('entry', 'mod_journal'), null, $this->_customdata['editoroptions']);
        $this->_form->setType('text_editor', PARAM_RAW);
        $this->_form->addRule('text_editor', null, 'required', null, 'client');

        $this->_form->addElement('hidden', 'id');
        $this->_form->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }
}
