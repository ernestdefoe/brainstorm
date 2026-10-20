import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import { saveAnswers, showAttempt, startAttempt, submitAttempt } from '../../common/api';
import type { Answer, Paper, PaperQuestion, Quiz, Result } from '../../common/api';
import { clock } from '../../common/format';
import ResultView from './ResultView';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-gauntlet.forum.player.${key}`, params);
const text = (key: string, params: Record<string, unknown> = {}) => extractText(t(key, params));

/** Answers wait this long after the last change before they are saved. */
const SAVE_AFTER = 2000;

interface Attrs {
  quiz: Quiz;
  /** Open this finished attempt's result instead of playing. */
  resultId?: number;
  onfinish?: (result: Result) => void;
  onclose?: () => void;
}

/**
 * Playing a quiz: one question at a time, answers saved as you go, and a
 * countdown when the quiz is timed.
 *
 * 🚨 The countdown is a display. The server set the deadline when the attempt
 * started and marks on its own clock; this one runs from the seconds the
 * server said were left, so a wrong clock on the device changes nothing. At
 * zero it submits what it has, and the server takes it within a short grace.
 */
export default class Player extends Component<Attrs> {
  phase: 'loading' | 'playing' | 'review' | 'submitting' | 'result' | 'error' = 'loading';
  paper: Paper | null = null;
  result: Result | null = null;
  answers: Record<string, Answer> = {};
  index = 0;
  endsAt: number | null = null;
  dirty = false;
  ticker = 0;
  saver = 0;
  error: string | null = null;

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);

    if (this.attrs.resultId) {
      showAttempt(this.attrs.resultId).then(({ data }) => (data.finished ? this.showResult(data) : this.play(data)), this.failed.bind(this));
    } else {
      this.begin();
    }
  }

  onremove(vnode: Mithril.VnodeDOM<Attrs, this>) {
    super.onremove(vnode);
    this.stop();
    // Leaving mid-quiz keeps what was answered.
    if (this.dirty && this.paper && this.phase !== 'result') saveAnswers(this.paper.id, this.answers).catch(() => {});
  }

  begin() {
    this.phase = 'loading';
    this.result = null;
    startAttempt(this.attrs.quiz.id).then(({ data }) => this.play(data), this.failed.bind(this));
  }

  play(paper: Paper) {
    this.paper = paper;
    this.answers = Array.isArray(paper.answers) ? {} : { ...paper.answers };
    this.index = Math.max(
      0,
      paper.questions.findIndex((q) => !this.answered(q))
    );
    if (this.attrs.quiz.me) this.attrs.quiz.me.openAttemptId = paper.id;
    this.phase = 'playing';

    if (paper.secondsLeft !== null) {
      this.endsAt = performance.now() + paper.secondsLeft * 1000;
      this.ticker = window.setInterval(() => {
        if (this.secondsLeft()! <= 0) this.submit();
        m.redraw();
      }, 1000);
    }

    m.redraw();
  }

  failed(error: any) {
    this.stop();
    this.phase = 'error';
    this.error = error?.response?.errors?.[0]?.detail || null;
    m.redraw();
  }

  stop() {
    window.clearInterval(this.ticker);
    window.clearTimeout(this.saver);
  }

  secondsLeft(): number | null {
    return this.endsAt === null ? null : Math.max(0, Math.ceil((this.endsAt - performance.now()) / 1000));
  }

  answered(q: PaperQuestion): boolean {
    const a = this.answers[q.id];
    return Array.isArray(a) ? a.length > 0 : !!(a && String(a).trim());
  }

  set(q: PaperQuestion, value: Answer) {
    this.answers[q.id] = value;
    this.dirty = true;
    window.clearTimeout(this.saver);
    this.saver = window.setTimeout(() => this.save(), SAVE_AFTER);
  }

  save() {
    if (!this.dirty || !this.paper || this.phase === 'result' || this.phase === 'submitting') return;
    this.dirty = false;
    saveAnswers(this.paper.id, this.answers).then(
      ({ data }) => {
        // Re-sync the countdown with the server's view of the time left.
        if (data.secondsLeft !== null && this.endsAt !== null) this.endsAt = performance.now() + data.secondsLeft * 1000;
      },
      () => (this.dirty = true)
    );
  }

  go(index: number) {
    this.save();
    this.index = index;
  }

  /** Next question, or from the last one to the check before submitting. */
  forward(last: boolean) {
    if (last) {
      this.save();
      this.phase = 'review';
    } else {
      this.go(this.index + 1);
    }
  }

  submit() {
    if (!this.paper || this.phase === 'submitting' || this.phase === 'result') return;
    this.stop();
    this.phase = 'submitting';
    this.dirty = false;

    submitAttempt(this.paper.id, this.answers).then(({ data }) => {
      this.showResult(data);
      this.attrs.onfinish?.(data);
    }, this.failed.bind(this));
  }

  showResult(result: Result) {
    this.result = result;
    this.phase = 'result';
    m.redraw();
  }

  view(): Mithril.Children {
    if (this.phase === 'loading' || this.phase === 'submitting') {
      return (
        <div className="GauntletPlayer GauntletPlayer--loading">
          <LoadingIndicator display="block" />
        </div>
      );
    }

    if (this.phase === 'error') {
      return (
        <div className="GauntletPlayer GauntletPlayer--error">
          <p>{this.error || t('error')}</p>
          {this.attrs.onclose ? (
            <Button className="Button" onclick={() => this.attrs.onclose!()}>
              {t('close')}
            </Button>
          ) : null}
        </div>
      );
    }

    if (this.phase === 'result') {
      return (
        <div className="GauntletPlayer">
          <ResultView result={this.result!} quiz={this.attrs.quiz} onretry={() => this.begin()} onclose={this.attrs.onclose} />
        </div>
      );
    }

    const paper = this.paper!;
    const total = paper.questions.length;
    const done = paper.questions.filter((q) => this.answered(q)).length;

    return (
      <div className="GauntletPlayer" aria-live="off">
        <div className="GauntletPlayer-bar">
          <span className="GauntletPlayer-count">
            {this.phase === 'review' ? t('review_heading') : t('question_of', { number: this.index + 1, total })}
          </span>
          {paper.practice ? <span className="GauntletChip">{t('practice')}</span> : null}
          {this.timer()}
          <div
            className="GauntletPlayer-progress"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={total}
            aria-valuenow={done}
            aria-label={text('answered', { count: done, total })}
          >
            <span style={{ width: (done / total) * 100 + '%' }} />
          </div>
        </div>

        {this.phase === 'review' ? this.reviewPanel(done, total) : this.question(paper.questions[this.index])}

        {this.dots()}
      </div>
    );
  }

  timer(): Mithril.Children {
    const left = this.secondsLeft();
    if (left === null) return null;

    const low = left <= Math.max(10, Math.min(60, (this.attrs.quiz.timeLimit || 0) * 0.1));

    return (
      <span className={'GauntletPlayer-timer' + (low ? ' is-low' : '')} role="timer" aria-label={text('time_left')}>
        <Icon name="far fa-clock" />
        {clock(left)}
      </span>
    );
  }

  question(q: PaperQuestion): Mithril.Children {
    const last = this.index === this.paper!.questions.length - 1;
    const name = `gauntlet-q${this.paper!.id}-${q.id}`;
    const value = this.answers[q.id];

    let input: Mithril.Children;
    if (q.type === 'text') {
      input = (
        <input
          className="FormControl GauntletPlayer-text"
          type="text"
          maxlength={300}
          autocomplete="off"
          aria-labelledby={name}
          placeholder={text('type_answer')}
          value={typeof value === 'string' ? value : ''}
          oninput={(e: InputEvent) => this.set(q, (e.target as HTMLInputElement).value)}
          onkeydown={(e: KeyboardEvent) => e.key === 'Enter' && this.forward(last)}
        />
      );
    } else {
      const multiple = q.type === 'multiple';
      const chosen: string[] = Array.isArray(value) ? value : value ? [value] : [];

      input = (
        <div className="GauntletOptions" role="group" aria-labelledby={name}>
          {multiple ? <p className="GauntletOptions-hint">{t('choose_all')}</p> : null}
          {q.options.map((o) => {
            const on = chosen.includes(o.id);

            return (
              <label className={'GauntletOption' + (on ? ' is-chosen' : '')} key={o.id}>
                <input
                  type={multiple ? 'checkbox' : 'radio'}
                  name={name}
                  value={o.id}
                  checked={on}
                  onchange={() => this.set(q, multiple ? (on ? chosen.filter((id) => id !== o.id) : [...chosen, o.id]) : o.id)}
                />
                <span className="GauntletOption-mark" aria-hidden="true" />
                <span className="GauntletOption-text">{q.type === 'boolean' ? t(o.id === 'true' ? 'true' : 'false') : o.text}</span>
              </label>
            );
          })}
        </div>
      );
    }

    return (
      <div className="GauntletQuestion" key={q.id}>
        <div className="GauntletQuestion-body Post-body" id={name}>
          {q.bodyHtml ? m.trust(q.bodyHtml) : null}
        </div>
        {q.imageUrl ? (
          <img className="GauntletQuestion-image" src={q.imageUrl} alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" />
        ) : null}
        {q.points !== 1 ? <p className="GauntletQuestion-points">{t('points', { count: q.points })}</p> : null}
        {input}
        <div className="GauntletPlayer-nav">
          <Button className="Button" icon="fas fa-arrow-left" disabled={this.index === 0} onclick={() => this.go(this.index - 1)}>
            {t('previous')}
          </Button>
          {last ? (
            <Button className="Button Button--primary" onclick={() => this.forward(true)}>
              {t('finish')}
            </Button>
          ) : (
            <Button className="Button Button--primary" onclick={() => this.go(this.index + 1)}>
              {t('next')}
            </Button>
          )}
        </div>
      </div>
    );
  }

  reviewPanel(done: number, total: number): Mithril.Children {
    const missing = total - done;

    return (
      <div className="GauntletPlayer-review">
        <p>{missing ? t('unanswered', { count: missing }) : t('all_answered')}</p>
        <div className="GauntletPlayer-nav">
          <Button className="Button" icon="fas fa-arrow-left" onclick={() => (this.phase = 'playing')}>
            {t('back')}
          </Button>
          <Button className="Button Button--primary" icon="fas fa-check" onclick={() => this.submit()}>
            {t('submit')}
          </Button>
        </div>
      </div>
    );
  }

  dots(): Mithril.Children {
    const questions = this.paper!.questions;
    if (questions.length < 2) return null;

    return (
      <nav className="GauntletDots" aria-label={text('jump')}>
        {questions.map((q, i) => (
          <button
            type="button"
            className={'GauntletDot' + (this.answered(q) ? ' is-answered' : '') + (i === this.index && this.phase === 'playing' ? ' is-current' : '')}
            aria-current={i === this.index && this.phase === 'playing' ? 'step' : undefined}
            aria-label={text('question_of', { number: i + 1, total: questions.length })}
            onclick={() => {
              this.go(i);
              this.phase = 'playing';
            }}
          >
            {i + 1}
          </button>
        ))}
      </nav>
    );
  }
}
