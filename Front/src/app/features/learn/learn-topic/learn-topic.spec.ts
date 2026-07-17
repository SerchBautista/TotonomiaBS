import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { TranslateModule } from '@ngx-translate/core';
import { By } from '@angular/platform-browser';
import { describe, expect, it, vi } from 'vitest';
import { LearnTopicComponent } from './learn-topic';
import { LearnContentService } from '../../../core/services/learn-content.service';

describe('LearnTopicComponent', () => {
  let fixture: ComponentFixture<LearnTopicComponent>;

  const setup = async (slug: string, disclaimer: string | null) => {
    const learnContentMock = {
      loadCatalog: vi.fn().mockReturnValue(of({
        cta: {
          title: 'CTA',
          subtitle: 'Sub',
          primary: 'Primary',
          secondary: 'Secondary',
        },
      })),
      loadTopic: vi.fn().mockReturnValue(of({
        eyebrow: 'Eyebrow',
        title: 'Topic title',
        lead: 'Topic lead',
        disclaimer,
        sections: [],
      })),
    };

    await TestBed.configureTestingModule({
      imports: [LearnTopicComponent, TranslateModule.forRoot()],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: of(convertToParamMap({ slug })),
          },
        },
        { provide: LearnContentService, useValue: learnContentMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(LearnTopicComponent);
    fixture.detectChanges();
  };

  it('renders disclaimer when topic has disclaimer text', async () => {
    await setup('tips', 'Important disclaimer');

    const disclaimerEl = fixture.debugElement.query(By.css('.learn-page__disclaimer'));
    expect(disclaimerEl).toBeTruthy();
  });

  it('does not render disclaimer when topic lacks disclaimer text', async () => {
    await setup('budgets', null);

    const disclaimerEl = fixture.debugElement.query(By.css('.learn-page__disclaimer'));
    expect(disclaimerEl).toBeFalsy();
  });
});
