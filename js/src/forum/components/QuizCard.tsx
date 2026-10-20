import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import type Mithril from 'mithril';
import { quizUrl } from '../../common/api';
import type { Quiz, Result } from '../../common/api';
import quizFacts from './QuizFacts';
import Player from './Player';
import { quizAction, quizStanding, recordFinish } from './QuizActions';

interface Attrs {
  quiz: Quiz;
  /** In a post: played where it is. In a list: a link to the quiz's page. */
  embedded?: boolean;
}

export default class QuizCard extends Component<Attrs> {
  playing = false;

  view(): Mithril.Children {
    const quiz = this.attrs.quiz;
    const embedded = this.attrs.embedded;

    return (
      <article className={'GauntletCard' + (embedded ? ' GauntletCard--embedded' : '') + (this.playing ? ' is-playing' : '')}>
        <header className="GauntletCard-header">
          <span className="GauntletCard-icon" aria-hidden="true">
            <Icon name="fas fa-graduation-cap" />
          </span>
          <div className="GauntletCard-heading">
            <h3 className="GauntletCard-title">
              <Link href={quizUrl(quiz)}>{quiz.title}</Link>
              {quiz.status === 'draft' ? (
                <span className="GauntletChip GauntletChip--draft">{app.translator.trans('ernestdefoe-gauntlet.forum.draft')}</span>
              ) : null}
            </h3>
            {quizFacts(quiz)}
          </div>
        </header>

        {this.playing ? (
          <Player
            quiz={quiz}
            onfinish={(r: Result) => recordFinish(quiz, r)}
            onclose={() => {
              this.playing = false;
            }}
          />
        ) : (
          [
            embedded && quiz.descriptionHtml ? <div className="GauntletCard-description Post-body">{m.trust(quiz.descriptionHtml)}</div> : null,
            <footer className="GauntletCard-footer">
              {quizStanding(quiz)}
              {quizAction(quiz, embedded ? () => (this.playing = true) : undefined)}
            </footer>,
          ]
        )}
      </article>
    );
  }
}
