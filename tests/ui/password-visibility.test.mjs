import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

// A small DOM adapter exercises the shipped script without a browser dependency.
class Element {
    constructor(tag='input',id='') { this.tagName=tag; this.id=id; this.type='password'; this.name=id; this.value='Secret123!'; this.dataset={}; this.attributes={}; this.events={}; this.children=[]; this.classes=new Set(); this.classList={contains:c=>this.classes.has(c),add:c=>this.classes.add(c),remove:c=>this.classes.delete(c)}; this.labels=[{textContent:id.replaceAll('_',' ')}]; }
    setAttribute(name,value) { this.attributes[name]=value; }
    addEventListener(name,listener) { this.events[name]=listener; }
    before(wrapper) { this.wrapper=wrapper; }
    append(child) { this.children.push(child); }
}
function setup(readyState='complete') {
    const fields=[new Element('input','current_password'),new Element('input','password'),new Element('input','password_confirmation')];
    fields[0].classes.add('mb-3');
    const events={}; const pageEvents={};
    const document={readyState,querySelectorAll:()=>fields,createElement:tag=>new Element(tag),addEventListener:(name,listener)=>events[name]=listener};
    runInNewContext(readFileSync(new URL('../../public/js/password-visibility.js',import.meta.url),'utf8'),{document,window:{addEventListener:(name,listener)=>pageEvents[name]=listener}});
    return {fields,events,pageEvents,button:field=>field.wrapper.children[1]};
}
test('each password toggles independently without changing its value or submitted name',()=>{
    const {fields,button}=setup(); const [current,password,confirmation]=fields;
    assert.equal(button(password).type,'button'); assert.equal(button(password).attributes['aria-controls'],'password');
    button(password).events.click(); assert.equal(password.type,'text'); assert.equal(current.type,'password'); assert.equal(confirmation.type,'password');
    assert.equal(password.value,'Secret123!'); assert.equal(password.name,'password'); assert.equal(button(password).attributes['aria-pressed'],'true'); assert.match(button(password).attributes['aria-label'],/^Hide /);
    button(password).events.click(); assert.equal(password.type,'password'); assert.equal(button(password).attributes['aria-pressed'],'false'); assert.match(button(password).attributes['aria-label'],/^Show /);
});
test('current and confirmation controls work, including blank optional edit passwords',()=>{
    const {fields,button}=setup(); fields[1].value=''; button(fields[0]).events.click(); button(fields[2]).events.click(); button(fields[1]).events.click();
    assert.equal(fields[0].type,'text'); assert.equal(fields[2].type,'text'); assert.equal(fields[1].value,'');
    assert.ok(fields[0].wrapper.classList.contains('mb-3')); assert.equal(fields[0].classList.contains('mb-3'),false);
});
test('submission, reset and restored pages re-mask all fields without altering credentials',()=>{
    for (const action of ['submit','reset','pageshow']) {
        const {fields,events,pageEvents,button}=setup(); fields.forEach(field=>button(field).events.click());
        (action==='pageshow' ? pageEvents:events)[action]();
        for (const field of fields) { assert.equal(field.type,'password'); assert.equal(field.value,'Secret123!'); assert.equal(button(field).attributes['aria-pressed'],'false'); }
    }
});
test('deferred initialization waits for DOM and does not add duplicate controls',()=>{
    const {fields,events}=setup('loading'); assert.equal(fields[0].wrapper,undefined); events.DOMContentLoaded(); const wrapper=fields[0].wrapper;
    events.DOMContentLoaded(); assert.equal(fields[0].wrapper,wrapper); assert.equal(wrapper.children.length,2);
});
