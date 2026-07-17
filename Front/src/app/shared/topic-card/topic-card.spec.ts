import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { TopicCardComponent } from './topic-card';

describe('TopicCardComponent', () => {
  let fixture: ComponentFixture<TopicCardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TopicCardComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(TopicCardComponent);
    const component = fixture.componentInstance;
    component.icon = 'fa-chart-pie';
    component.title = 'Budgeting';
    component.summary = 'Learn to budget';
    component.link = '/learn/budgeting';
    component.readMoreLabel = 'Read more';
    fixture.detectChanges();
  });

  it('renders topic content and navigates to the topic link', () => {
    const link = fixture.nativeElement.querySelector('a.topic-card') as HTMLAnchorElement;
    expect(link.textContent).toContain('Budgeting');
    expect(link.textContent).toContain('Learn to budget');
    expect(link.textContent).toContain('Read more');
    expect(link.getAttribute('href')).toContain('/learn/budgeting');
  });
});
