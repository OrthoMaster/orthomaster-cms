import './bootstrap';

import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import List from '@editorjs/list';
import Image from '@editorjs/image';
import Quote from '@editorjs/quote';
import Code from '@editorjs/code';

window.EditorJS = EditorJS;
window.EditorJSTools = {
    Header,
    List,
    Image,
    Quote,
    Code
};