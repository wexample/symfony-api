import Page from '@wexample/symfony-loader/js/Class/Page';
import { initCodeBlocks } from '@wexample/symfony-content/ts/code-block';

export default class extends Page {
    async pageReady() {
        await initCodeBlocks(this.el);
    }
}
