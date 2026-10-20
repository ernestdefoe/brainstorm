import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import Leaderboard from '../components/Leaderboard';

const t = (key: string) => app.translator.trans(`ernestdefoe-gauntlet.forum.leaderboard.${key}`);

/** /quizzes/leaderboard — quizzes passed, across every quiz. */
export default class LeaderboardPage extends Page {
  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.setTitle(extractText(t('overall')));
  }

  view(): Mithril.Children {
    return (
      <PageStructure className="GauntletLeaderboardPage" sidebar={() => <IndexSidebar />}>
        <div className="GauntletIndexPage-head">
          <h2 className="GauntletIndexPage-title">{t('overall')}</h2>
          <LinkButton className="Button" icon="fas fa-arrow-left" href={app.route('gauntlet.index')}>
            {t('back')}
          </LinkButton>
        </div>
        <p className="helpText">{t('overall_help')}</p>
        <Leaderboard />
      </PageStructure>
    );
  }
}
